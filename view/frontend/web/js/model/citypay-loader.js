define(['jquery'], function ($) {
    'use strict';

    var loading;

    function getSdk() {
        return window.CityPay || window.citypay;
    }

    function waitForSdk(deferred, startedAt) {
        var sdk = getSdk();

        if (sdk) {
            deferred.resolve(sdk);
            return;
        }

        if (Date.now() - startedAt >= 10000) {
            loading = null;
            deferred.reject(new Error('CityPay SDK loaded but no CityPay global was found'));
            return;
        }

        window.setTimeout(function () {
            waitForSdk(deferred, startedAt);
        }, 100);
    }

    return function () {
        var sdk = getSdk();

        if (sdk) {
            return $.Deferred().resolve(sdk).promise();
        }

        if (loading) {
            return loading.promise();
        }

        loading = $.Deferred();

        var script = document.createElement('script');

        script.src = 'https://js.citypay.com/v2/loader/2.1/citypay.js';
        // script.src = 'https://localhost:8080/';

        script.async = true;

        script.onload = function () {
            waitForSdk(loading, Date.now());
        };

        script.onerror = function () {
            var deferred = loading;

            loading = null;
            deferred.reject(new Error('Failed to load CityPay SDK'));
        };

        document.head.appendChild(script);

        return loading.promise();
    };
});
