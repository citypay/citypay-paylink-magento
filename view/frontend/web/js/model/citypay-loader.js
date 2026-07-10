define(['jquery'], function ($) {
    'use strict';

    var loading;

    function getSdk() {
        return window.CityPay || window.citypay;
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

        script.src = 'https://js.citypay.com/v2/loader/2.0.12/citypay.js';
        script.async = true;

        script.onload = function () {
            var loadedSdk = getSdk();

            if (loadedSdk) {
                loading.resolve(loadedSdk);
            } else {
                loading.reject(new Error('CityPay SDK loaded but no CityPay global was found'));
            }
        };

        script.onerror = function () {
            loading.reject(new Error('Failed to load CityPay SDK'));
        };

        document.head.appendChild(script);

        return loading.promise();
    };
});