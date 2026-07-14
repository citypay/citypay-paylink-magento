/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'require',
        'jquery',
        'Magento_Checkout/js/view/payment/default',
        'Magento_Checkout/js/action/place-order',
        'CityPay_Paylink/js/action/get-pltoken',
        'Magento_Checkout/js/model/payment/additional-validators',
        'Magento_Checkout/js/action/redirect-on-success',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'CityPay_Paylink/js/model/citypay-loader'
    ],
    function (require, $,Component, placeOrderAction,getplTokenAction,additionalValidators,redirectOnSuccessAction,  urlBuilder, storage, loadCityPay) {
        'use strict';

        const config = window.checkoutConfig.payment.citypay_gateway;
        const pubKey = config.pubKey;
        const elementsStyle = config.elementsStyle || 'row';

        return Component.extend({
            defaults: {
                template: 'CityPay_Paylink/payment/form',
                transactionResult: '',
                orderId: ''
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult'
                    ]);

                this.isChecked.subscribe(function () {
                    this.loadCityPayIfSelected();
                }, this);

                return this;
            },

            getCode: function () {
                return 'citypay_gateway';
            },

            isSelected: function () {
                return this.getCode() === this.isChecked();
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'transaction_result': this.transactionResult(),
                        'orderId': this.orderId
                    }
                };
            },

            // Which payment method has been selected
            getPaymentMode: function () {
                return window.checkoutConfig.payment.citypay_gateway.paymentMode;
            },

            isPaylinkMode: function () {
                return this.getPaymentMode() === 'paylink';
            },

            isElementsMode: function () {
                return this.getPaymentMode() === 'elements';
            },

            loadCityPayIfSelected: function () {
                if (this.isElementsMode() && this.isSelected()) {
                    return this.initCityPayElements();
                }
                console.log("Start loading CityPay SDK");

                return $.Deferred().resolve().promise();
            },

            onCardFormRendered: function () {
                this.loadCityPayIfSelected();
            },

            selectPaymentMethod: function () {
                const result = this._super();
                this.loadCityPayIfSelected();
                return result;
            },

            createElementsSession: function () {
                const self = this;
                console.log("CityPay:Elements:in citypay_gateway.js, calling elements/payment-session");

                return storage.post(
                    urlBuilder.createUrl('/citypay/elements/payment-session', {}),
                    JSON.stringify({})
                ).then(function (response) {
                    response = typeof response === 'string' ? JSON.parse(response) : response;
                    self.paymentIntentId = response.paymentIntentId;
                    return response;
                });
            },

            authorisePayment: function (paymentIntentId) {
                console.log("CityPay:Elements:in authorisePayment");
                return storage.post(
                    urlBuilder.createUrl('/citypay/elements/authorise', {}),
                    JSON.stringify({
                        paymentIntentId: paymentIntentId
                    })
                ).then(function (response) {
                    return typeof response === 'string' ? JSON.parse(response) : response;
                });
            },

            verify: function (paymentIntentId) {
                console.log("CityPay:Elements:in verify");
                return storage.post(
                    urlBuilder.createUrl('/citypay/elements/verify', {}),
                    JSON.stringify({
                        paymentIntentId: paymentIntentId
                    })
                ).then(function (response) {
                    return typeof response === 'string' ? JSON.parse(response) : response;
                });
            },

            initCityPayElements: function () {
                console.log("in initCityPayElements");
                const self = this;
                const config = window.checkoutConfig.payment.citypay_gateway;

                if (!this.isElementsMode()) {
                    return $.Deferred().resolve().promise();
                }

                if (this.card) {
                    return $.Deferred().resolve(this.card).promise();
                }

                if (this.elementsLoading) {
                    return this.elementsLoading.promise();
                }

                this.elementsLoading = $.Deferred();

                window.setTimeout(function () {
                    if (!document.getElementById('card-form')) {
                        self.elementsLoading.reject(new Error('CityPay card container was not rendered'));
                        return;
                    }

                    loadCityPay()
                        .then(function (citypay) {
                            self.citypay = citypay;

                            console.log("CityPay loaded, will start creating a payment session")

                            return citypay.elements({
                                pubKey: pubKey,
                                createServerIntent: function () {
                                    return self.createElementsSession();
                                },
                                eager: true,
                            });
                        })
                        .then(function (elements) {
                            console.log("creating elements");

                            self.card = elements.cardElement({
                                identifier: 'default',
                                element: '#card-form',
                                layout: elementsStyle || 'row'
                            });

                            console.log("Elements: init...");
                            return self.card.init();
                        })
                        .then(function () {
                            console.log("Elements: await...");
                            return self.card.awaitReady();
                        })
                        .then(function () {
                            self.elementsLoading.resolve(self.card);
                        })
                        .fail(function (error) {
                            self.elementsLoading.reject(error);
                            console.log("An error occurred: ", error);
                        });
                }, 0);

                return this.elementsLoading.promise();
            },

            placeOrder:function (data, event) {
                var self = this;
                //alert('my placeOrder');

                if (event) {
                    event.preventDefault();
                }

                if (this.validate() &&
                    additionalValidators.validate() &&
                    this.isPlaceOrderActionAllowed() === true
                ) {
                    this.isPlaceOrderActionAllowed(false);
                    this.getPlaceOrderDeferredObject()
                        .then(
                            function (value) {
                                //alert('orderId '+value)
                                //self.afterPlaceOrder();
                                self.orderId = value;

                                if (self.isPaylinkMode()) {
                                    console.log("Placing order for Paylink")
                                    self.getPLTokenDeferredObject();
                                    /*
                                    .then(function(value){
                                        alert('pltoken result'+value)
                                    })
                                    .done(
                                        function () {
                                            self.afterPlaceOrder();

                                            if (self.redirectAfterPlaceOrder) {
                                                redirectOnSuccessAction.execute();
                                            }
                                        }
                                    ).always(
                                    function () {
                                        self.isPlaceOrderActionAllowed(true);
                                    }
                                );*/
                                    /*
                                                                    if (self.redirectAfterPlaceOrder) {
                                                                        redirectOnSuccessAction.execute();
                                                                    }

                                     */
                                } else if (self.isElementsMode()) {
                                    console.log("Placing Order for ElementsPaymentManagement");

                                    self.card.tokenise()
                                        .then(function (tokeniseResponse) {
                                            const token = tokeniseResponse.data.cp_card_token;

                                            return self.card.attach({
                                                intentId: self.paymentIntentId,
                                                token: token
                                            });
                                        })
                                        .then(function () {
                                            self.card.confirm({
                                                intentId: self.paymentIntentId,
                                            }).then(function (confirmResult) {
                                                console.log("CityPay:Elements: confirm result: ", confirmResult);

                                                if (confirmResult.status !== 'requires_authorisation') {
                                                    return;
                                                }

                                                return self.authorisePayment(self.paymentIntentId);
                                            }).then(function (auth) {
                                                console.log('Authorise result', auth);
                                                return self.verify(self.paymentIntentId);
                                            }).then(function (verifyResult) {
                                                console.log("CityPay:Elements: verified result: ", verifyResult);
                                            });
                                        });
                                }
                            }
                        ).always(
                        function () {
                            self.isPlaceOrderActionAllowed(true);
                        }
                    );



                    return true;
                }

                return false;
            },
            /**
             * @return {*}
             */
            getPlaceOrderDeferredObject: function () {
                return $.when(
                    //alert('$ when1'),
                    placeOrderAction(this.getData(), this.messageContainer)
                );
            },
            getPLTokenDeferredObject:function(){
                return $.when(
                    //alert('$ when2'),
                    getplTokenAction(this.getData(),this.messageContainer)
                );
            },
            getTransactionResults: function() {
                return _.map(window.checkoutConfig.payment.citypay_gateway.transactionResults, function(value, key) {
                    return {
                        'value': key,
                        'transaction_result': value
                    }
                });
            },
        });
    }
);
