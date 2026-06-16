import Echo from 'laravel-echo';
import client from 'socket.io-client';

// Real-time push (laravel-echo-server over websocket) is not wired on this
// deployment — VITE_ECHO_ADDRESS is a placeholder (localhost:8443, leftover
// from the shelved InspIRCd stack) with no echo-server behind it. Connecting
// anyway made every browser hammer a dead wss:// endpoint: console spam + slow
// chat init, with a 5s reconnect loop.
//
// So: only stand up a real Echo client when VITE_ECHO_ADDRESS points at a real
// (non-localhost) host. Otherwise install a no-op stub that satisfies the
// chained channel API chatbox.js uses (join/private/listen/here/...), so the
// REST-based chat (load + post messages, bots, stats) keeps working untouched —
// it just won't receive live push. Wire laravel-echo-server + set a real
// VITE_ECHO_ADDRESS to enable true real-time, then rebuild.
const echoAddress = import.meta.env.VITE_ECHO_ADDRESS;
const echoEnabled = Boolean(echoAddress) && !/(^|\/\/)(localhost|127\.0\.0\.1)(:|\/|$)/.test(echoAddress);

if (echoEnabled) {
    window.io = client;

    window.Echo = new Echo({
        broadcaster: 'socket.io',
        host: echoAddress,
        forceTLS: true,
        withCredentials: true,
        transports: ['websocket'],
        enabledTransports: ['wss'],
    });
} else {
    // No-op channel: every method is chainable and does nothing.
    const channel = {};
    const passthrough = () => channel;

    Object.assign(channel, {
        here: passthrough,
        joining: passthrough,
        leaving: passthrough,
        listen: passthrough,
        listenForWhisper: passthrough,
        stopListening: passthrough,
        subscribed: passthrough,
        error: passthrough,
        whisper: passthrough,
    });

    window.Echo = {
        join: () => channel,
        private: () => channel,
        channel: () => channel,
        leave: () => {},
        leaveChannel: () => {},
        disconnect: () => {},
        // Axios/Livewire read Echo.socketId() to set the X-Socket-ID header on
        // requests (random-media refresh, rank pages, etc.). With no live socket
        // there is no id — return null so the header is simply omitted. Omitting
        // this method is what silently broke those features in the 2026-06-01 build.
        socketId: () => null,
    };
}
