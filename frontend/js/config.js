/**
 * Configuration frontend.
 * API_URL est calculé dynamiquement pour fonctionner :
 *  - en local        : http://localhost:8001
 *  - dans l'aperçu   : https://{port}-{sandbox}.e2b.app -> port backend 8001
 *
 * Le port backend peut être surchargé via `window.API_PORT` (utilisé par les
 * tests d'intégration pour viser une autre instance, par exemple le backend
 * Laravel en cours de migration). Par défaut : 8001.
 */
(function () {
    const loc = window.location;
    const port = window.API_PORT || '8001';
    const m = loc.hostname.match(/^(\d+)-(.+)$/); // proxy e2b : 8080-xxx.e2b.app
    if (m) {
        window.API_URL = loc.protocol + '//' + port + '-' + m[2];
    } else {
        window.API_URL = 'http://localhost:' + port;
    }
})();
