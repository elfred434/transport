/**
 * Configuration frontend.
 * API_URL est calculé dynamiquement pour fonctionner :
 *  - en local        : http://localhost:8001
 *  - dans l'aperçu   : https://{port}-{sandbox}.e2b.app -> port backend 8001
 */
(function () {
    const loc = window.location;
    const m = loc.hostname.match(/^(\d+)-(.+)$/); // proxy e2b : 8080-xxx.e2b.app
    if (m) {
        window.API_URL = loc.protocol + '//8001-' + m[2];
    } else {
        window.API_URL = 'http://localhost:8001';
    }
})();
