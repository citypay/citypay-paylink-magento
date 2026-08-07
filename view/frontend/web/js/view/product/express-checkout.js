define([
    'jquery',
    'mage/storage',
    'CityPay_Paylink/js/model/citypay-loader',
    'Magento_Checkout/js/model/url-builder',
], function ($, storage, loadCityPay, urlBuilder) {
    'use strict';

    function parseResponse(response) {
        var result = response;

        // Magento may serialize a PHP string response one extra time.
        while (typeof result === 'string') {
            result = JSON.parse(result);
        }

        return result;
    }

    function createCheckoutSession () {
        console.log("CityPay:createCheckoutSession:in express-checkout.js, calling elements/createCheckoutSession");

        return storage.post(
            urlBuilder.createUrl('/citypay/elements/createCheckoutSession', {}),
            JSON.stringify({})
        ).then(function (response) {
            response = typeof response === 'string' ? JSON.parse(response) : response;
            self.checkoutContextId = response.checkoutContextId;
            return response;
        });
    }

    return function (config, element) {
        var $element = $(element);
        var $message = $element.find(
            '#citypay-product-express-message'
        );

        function showError(message) {
            $message.text(message);
            $element.attr('aria-busy', 'false');
        }

        if (!config.checkoutContextUrl) {
            showError('CityPay checkout context URL is missing.');
            return;
        }

        $element.attr('aria-busy', 'true');
        $message.empty();

        loadCityPay()
            .then(function (citypay) {
                self.citypay = citypay;

                console.log("CityPay loaded, will start creating a checkout session");

                return createCheckoutSession()
                    .then(function (checkoutSession) {
                        console.log("not sure what to do: ", checkoutSession);
                    })
            })
            .fail(function (error) {
                console.error('CityPay express checkout initialization failed:', error);

                showError('Express checkout is currently unavailable.');
            });
    };
});
