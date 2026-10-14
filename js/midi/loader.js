/*
 * MIDI.Plugin — modernized compatibility layer
 *
 * Keeps the legacy MIDI.js API while adding:
 * - Promise support: MIDI.loadPlugin(opts) resolves when loading completes
 * - Safe defaults for optional callbacks and options
 * - Better errors when no supported audio backend is available
 * - Correct instrument IDs in progress callbacks
 * - Duplicate instrument removal
 * - No dependency on `window` when checking globals
 */

var MIDI = typeof MIDI !== 'undefined' ? MIDI : {};

MIDI.Soundfont = MIDI.Soundfont || {};
MIDI.Player = MIDI.Player || {};

(function (root) {
    'use strict';

    root.DEBUG = root.DEBUG !== false;
    root.USE_XHR = root.USE_XHR !== false;
    root.soundfontUrl = root.soundfontUrl || './soundfont/';

    function getGlobal(name) {
        if (typeof globalThis !== 'undefined') return globalThis[name];
        if (typeof window !== 'undefined') return window[name];
        return undefined;
    }

    function getLocationHash() {
        var location = getGlobal('location');
        return location && location.hash ? location.hash.slice(1) : '';
    }

    function makeError(message) {
        return new Error('[MIDI.js] ' + message);
    }

    /**
     * Load MIDI support and requested soundfonts.
     *
     * Supports both callbacks and Promises:
     * MIDI.loadPlugin({ onsuccess: fn, onerror: fn });
     * MIDI.loadPlugin({ instrument: 'acoustic_grand_piano' }).then(...);
     */
    root.loadPlugin = function (options) {
        if (typeof options === 'function') {
            options = { onsuccess: options };
        }

        options = options || {};

        return new Promise(function (resolve, reject) {
            var opts = Object.assign({}, options);
            var userSuccess = opts.onsuccess;
            var userError = opts.onerror;

            opts.onsuccess = function () {
                if (typeof userSuccess === 'function') userSuccess();
                resolve(root);
            };

            opts.onerror = function (error) {
                var normalizedError = error instanceof Error
                    ? error
                    : makeError(String(error || 'Unable to load MIDI resources.'));

                if (typeof userError === 'function') userError(normalizedError);
                reject(normalizedError);
            };

            root.soundfontUrl = opts.soundfontUrl || root.soundfontUrl;

            if (typeof root.audioDetect !== 'function') {
                opts.onerror(makeError('audioDetect() is not available.'));
                return;
            }

            root.audioDetect(function (supports) {
                supports = supports || {};

                var requestedApi = opts.api;
                var hashApi = getLocationHash();
                var api = '';

                if (requestedApi && supports[requestedApi]) {
                    api = requestedApi;
                } else if (hashApi && supports[hashApi]) {
                    api = hashApi;
                } else if (supports.webmidi) {
                    api = 'webmidi';
                } else if (getGlobal('AudioContext') || getGlobal('webkitAudioContext')) {
                    api = 'webaudio';
                } else if (getGlobal('Audio')) {
                    api = 'audiotag';
                }

                if (!api || !connect[api]) {
                    opts.onerror(makeError('No supported MIDI audio backend was found.'));
                    return;
                }

                var format = opts.targetFormat ||
                    (supports['audio/ogg'] ? 'ogg' : 'mp3');

                root.__api = api;
                root.__audioFormat = format;
                root.supports = supports;

                try {
                    root.loadResource(opts);
                } catch (error) {
                    opts.onerror(error);
                }
            });
        });
    };

    root.loadResource = function (options) {
        var opts = Object.assign({}, options || {});
        var instruments = opts.instruments || opts.instrument || 'acoustic_grand_piano';

        if (!Array.isArray(instruments)) {
            instruments = instruments === 0 || instruments ? [instruments] : [];
        }

        instruments = instruments.map(function (instrument) {
            if (typeof instrument === 'number' || /^\d+$/.test(String(instrument))) {
                var program = root.GM && root.GM.byId
                    ? root.GM.byId[Number(instrument)]
                    : null;

                return program && program.id ? program.id : instrument;
            }
            return instrument;
        });

        // Avoid loading the same soundfont more than once.
        instruments = instruments.filter(function (instrument, index) {
            return instruments.indexOf(instrument) === index;
        });

        opts.format = opts.format || root.__audioFormat;
        opts.instruments = instruments;

        var api = root.__api;
        if (!api || !connect[api]) {
            throw makeError('No MIDI backend has been selected. Call loadPlugin() first.');
        }

        connect[api](opts);
    };

    var connect = {
        webmidi: function (opts) {
            if (!root.WebMIDI || typeof root.WebMIDI.connect !== 'function') {
                opts.onerror && opts.onerror(makeError('WebMIDI backend is unavailable.'));
                return;
            }
            root.WebMIDI.connect(opts);
        },

        audiotag: function (opts) {
            requestQueue(opts, 'AudioTag');
        },

        webaudio: function (opts) {
            requestQueue(opts, 'WebAudio');
        }
    };

    function requestQueue(opts, context) {
        var instruments = opts.instruments || [];
        var total = instruments.length;
        var completed = 0;
        var failed = false;

        function finishOne() {
            completed++;

            if (typeof opts.onprogress === 'function') {
                opts.onprogress('load', total ? completed / total : 1);
            }

            if (completed === total) {
                if (typeof opts.onprogress === 'function') {
                    opts.onprogress('load', 1);
                }

                if (!root[context] || typeof root[context].connect !== 'function') {
                    opts.onerror && opts.onerror(makeError(context + ' backend is unavailable.'));
                    return;
                }

                root[context].connect(opts);
            }
        }

        if (!total) {
            if (typeof opts.onprogress === 'function') opts.onprogress('load', 1);

            if (!root[context] || typeof root[context].connect !== 'function') {
                opts.onerror && opts.onerror(makeError(context + ' backend is unavailable.'));
                return;
            }

            root[context].connect(opts);
            return;
        }

        instruments.forEach(function (instrumentId) {
            if (root.Soundfont[instrumentId]) {
                finishOne();
                return;
            }

            sendRequest(
                instrumentId,
                opts.format,
                function (event, progress) {
                    if (typeof opts.onprogress === 'function') {
                        var partial = Math.max(0, Math.min(1, Number(progress) || 0));
                        opts.onprogress(
                            'load',
                            (completed + partial) / total,
                            instrumentId
                        );
                    }
                },
                finishOne,
                function (error) {
                    if (failed) return;
                    failed = true;
                    if (typeof opts.onerror === 'function') {
                        opts.onerror(error || makeError('Failed to load ' + instrumentId + '.'));
                    }
                }
            );
        });
    }

    function sendRequest(instrumentId, audioFormat, onprogress, onsuccess, onerror) {
        var baseUrl = root.soundfontUrl;
        var separator = baseUrl.charAt(baseUrl.length - 1) === '/' ? '' : '/';
        var soundfontPath = baseUrl + separator + instrumentId + '-' + audioFormat + '.js';

        if (root.USE_XHR) {
            if (!root.util || typeof root.util.request !== 'function') {
                onerror(makeError('XHR utility is unavailable.'));
                return;
            }

            root.util.request({
                url: soundfontPath,
                format: 'text',
                onerror: onerror,
                onprogress: onprogress,
                onsuccess: function (event, responseText) {
                    try {
                        var script = document.createElement('script');
                        script.type = 'text/javascript';
                        script.text = responseText;
                        (document.body || document.head || document.documentElement)
                            .appendChild(script);
                        onsuccess();
                    } catch (error) {
                        onerror(error);
                    }
                }
            });
            return;
        }

        if (!root.dom || !root.dom.loadScript || typeof root.dom.loadScript.add !== 'function') {
            onerror(makeError('Script loader is unavailable.'));
            return;
        }

        root.dom.loadScript.add({
            url: soundfontPath,
            verify: 'MIDI.Soundfont["' + instrumentId + '"]',
            onerror: onerror,
            onsuccess: onsuccess
        });
    }

    root.setDefaultPlugin = function (midi) {
        if (!midi) return;

        Object.keys(midi).forEach(function (key) {
            root[key] = midi[key];
        });
    };
})(MIDI);