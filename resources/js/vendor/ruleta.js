// La ruleta del mirón (página de donar). Sólo expone la rueda como global: el
// componente Alpine vive en la vista, porque tiene que registrarse en
// `alpine:init` y este módulo se evalúa después de que Alpine haya arrancado.
import { Wheel } from './spin-wheel/spin-wheel-esm.js';

window.SpinWheel = Wheel;
