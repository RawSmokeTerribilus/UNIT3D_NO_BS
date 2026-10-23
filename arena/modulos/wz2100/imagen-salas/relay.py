#!/usr/bin/env python3
"""Arena NOBS — relé de sesiones reanudables (sustituye a websockify).

El navegador habla WebSocket con el relé y el relé habla TCP con el host de la sala. La
diferencia con websockify: si el WebSocket se cae (el móvil cambia de app, se va la wifi,
una pestaña dormida), el relé NO cierra el TCP con el host. Guarda lo que llegue y espera
hasta ARENA_REANUDAR_SEG a que el mismo socio vuelva a engancharse a su sesión. Como
el host nunca ve una desconexión, la partida solo se pausa (lockstep) y luego sigue.

Protocolo (tramas binarias del WebSocket, enteros big-endian):
  0x01 DATOS   seq:u32 + bytes     datos del flujo; seq empieza en 1 en cada sentido
  0x02 ACK     seq:u32             «he recibido hasta seq» (permite tirar lo confirmado)
  0x03 HOLA    recibido:u32 + id   servidor → cliente al (re)engancharse: hasta dónde le llegó
                                   del cliente + id de sesión (32 hex)
  0x04 CIERRE  —                   cliente → servidor: el juego cerró el socket a propósito
  0x05 FIN     motivo utf-8        servidor → cliente: la sesión ya no existe; no reintentar

Conexión nueva:   /ws?token=salaN
Reanudar:         /ws?token=salaN&sesion=<id>&recibido=<último seq de DATOS entregado al juego>
La sesión va atada al socio (cabecera X-Arena-User, que pone el nginx de UNIT3D).
"""

import asyncio
import os
import secrets
import struct
import time
from collections import deque
from urllib.parse import parse_qs, urlparse

from websockets.asyncio.server import serve
from websockets.exceptions import ConnectionClosed

FICHERO_TOKENS = os.environ.get('ARENA_FICHERO_TOKENS', '/tmp/arena/tokens')
PUERTO = int(os.environ.get('ARENA_PUERTO_WS', '8080'))
REANUDAR_SEG = int(os.environ.get('ARENA_REANUDAR_SEG', '7200'))
TOPE_PENDIENTE = int(os.environ.get('ARENA_TOPE_PENDIENTE_MB', '16')) * 1024 * 1024

DATOS, ACK, HOLA, CIERRE, FIN = 1, 2, 3, 4, 5
ACK_CADA = 32

sesiones = {}


def log(msg):
    print(time.strftime('%Y-%m-%dT%H:%M:%S'), 'relé:', msg, flush=True)


def destino(token):
    """salaN → (host, puerto) según el fichero de tokens que escribe el agente."""
    try:
        with open(FICHERO_TOKENS, encoding='utf-8') as f:
            for linea in f:
                nombre, _, resto = linea.partition(':')
                if nombre.strip() == token:
                    host, _, puerto = resto.strip().rpartition(':')
                    return host, int(puerto)
    except OSError:
        pass
    return None


class Sesion:
    def __init__(self, sid, socio, token, lector, escritor):
        self.id = sid
        self.socio = socio
        self.token = token
        self.lector = lector
        self.escritor = escritor
        self.ws = None
        self.desenganchada_desde = None
        self.seq_envio = 0              # último seq enviado al cliente
        self.pendiente = deque()        # [(seq, bytes)] sin ACK del cliente
        self.bytes_pendientes = 0
        self.esperado = 1               # siguiente seq que esperamos del cliente
        self.sin_ack = 0
        self.cerrada = False
        self.tarea_host = asyncio.create_task(self._leer_host())

    async def _leer_host(self):
        try:
            while True:
                datos = await self.lector.read(65536)
                if not datos:
                    break
                self.seq_envio += 1
                self.pendiente.append((self.seq_envio, datos))
                self.bytes_pendientes += len(datos)
                if self.bytes_pendientes > TOPE_PENDIENTE:
                    log(f'{self.id[:8]} ({self.socio}) supera {TOPE_PENDIENTE // 1048576} MB pendientes; se cierra')
                    break
                if self.ws is not None:
                    try:
                        await self.ws.send(struct.pack('>BI', DATOS, self.seq_envio) + datos)
                    except ConnectionClosed:
                        pass
        except (ConnectionError, OSError):
            pass
        await self.cerrar('el host cerró la conexión')

    def confirmar(self, hasta):
        while self.pendiente and self.pendiente[0][0] <= hasta:
            _, datos = self.pendiente.popleft()
            self.bytes_pendientes -= len(datos)

    async def enganchar(self, ws, recibido_cliente):
        if self.ws is not None and self.ws is not ws:
            try:
                await self.ws.close(4000, 'sustituida por una reconexión')
            except ConnectionClosed:
                pass
        self.ws = ws
        self.desenganchada_desde = None
        self.confirmar(recibido_cliente)
        await ws.send(struct.pack('>BI', HOLA, self.esperado - 1) + self.id.encode())
        for seq, datos in list(self.pendiente):
            await ws.send(struct.pack('>BI', DATOS, seq) + datos)

    def desenganchar(self, ws):
        if self.ws is ws:
            self.ws = None
            self.desenganchada_desde = time.monotonic()

    async def del_cliente(self, trama):
        tipo = trama[0]
        if tipo == DATOS and len(trama) >= 5:
            seq = struct.unpack('>I', trama[1:5])[0]
            if seq == self.esperado:
                self.escritor.write(trama[5:])
                await self.escritor.drain()
                self.esperado += 1
                self.sin_ack += 1
                if self.sin_ack >= ACK_CADA and self.ws is not None:
                    self.sin_ack = 0
                    await self.ws.send(struct.pack('>BI', ACK, self.esperado - 1))
            # seq < esperado: duplicado tras reanudar → se ignora
        elif tipo == ACK and len(trama) >= 5:
            self.confirmar(struct.unpack('>I', trama[1:5])[0])
        elif tipo == CIERRE:
            await self.cerrar('el jugador cerró el juego')

    async def cerrar(self, motivo):
        if self.cerrada:
            return
        self.cerrada = True
        sesiones.pop(self.id, None)
        log(f'{self.id[:8]} ({self.socio}, {self.token}) cerrada: {motivo}')
        try:
            self.escritor.close()
        except OSError:
            pass
        if self.ws is not None:
            try:
                await self.ws.send(bytes([FIN]) + motivo.encode())
                await self.ws.close(1000, 'fin')
            except ConnectionClosed:
                pass


async def atender(ws):
    url = urlparse(ws.request.path)
    q = {k: v[0] for k, v in parse_qs(url.query).items()}
    socio = ws.request.headers.get('X-Arena-User', '') or 'anónimo'
    token = q.get('token', '')

    if 'sesion' in q:
        sesion = sesiones.get(q['sesion'])
        if sesion is None or sesion.socio != socio or sesion.token != token:
            await ws.send(bytes([FIN]) + b'sesion caducada o desconocida')
            await ws.close(1000, 'sin sesion')
            return
        recibido = int(q.get('recibido', '0') or 0)
        fuera = time.monotonic() - sesion.desenganchada_desde if sesion.desenganchada_desde else 0
        log(f'{sesion.id[:8]} ({socio}, {token}) reanudada tras {fuera:.0f} s')
    else:
        dst = destino(token)
        if dst is None:
            await ws.send(bytes([FIN]) + b'sala desconocida')
            await ws.close(1000, 'sin sala')
            return
        try:
            lector, escritor = await asyncio.open_connection(*dst)
        except OSError as e:
            await ws.send(bytes([FIN]) + f'la sala no responde: {e}'.encode())
            await ws.close(1000, 'sala caida')
            return
        sesion = Sesion(secrets.token_hex(16), socio, token, lector, escritor)
        sesiones[sesion.id] = sesion
        recibido = 0
        log(f'{sesion.id[:8]} ({socio}, {token}) nueva → {dst[0]}:{dst[1]}')

    await sesion.enganchar(ws, recibido)
    try:
        async for trama in ws:
            if isinstance(trama, (bytes, bytearray)) and trama:
                await sesion.del_cliente(bytes(trama))
            if sesion.cerrada:
                break
    except ConnectionClosed:
        pass
    finally:
        sesion.desenganchar(ws)


async def conserje():
    """Cierra las sesiones que llevan más de REANUDAR_SEG sin cliente."""
    while True:
        await asyncio.sleep(30)
        ahora = time.monotonic()
        for sesion in list(sesiones.values()):
            if sesion.desenganchada_desde and ahora - sesion.desenganchada_desde > REANUDAR_SEG:
                await sesion.cerrar(f'sin volver en {REANUDAR_SEG} s')


async def main():
    log(f'escuchando en :{PUERTO}; reanudación hasta {REANUDAR_SEG} s')
    asyncio.create_task(conserje())
    async with serve(atender, '0.0.0.0', PUERTO, max_size=2 ** 20, ping_interval=20, ping_timeout=20):
        await asyncio.Future()


if __name__ == '__main__':
    asyncio.run(main())
