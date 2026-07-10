/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
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
    function ($,Component, placeOrderAction,getplTokenAction,additionalValidators,redirectOnSuccessAction,  urlBuilder, storage, loadCityPay) {
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
                return this;
            },

            getCode: function () {
                return 'citypay_gateway';
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

            createElementsSession: function () {
                return storage.post(
                    urlBuilder.createUrl('/citypay/elements/payment-session', {}),
                    JSON.stringify({})
                ).then(function (response) {
                    return typeof response === 'string' ? JSON.parse(response) : response;
                });
            },

            initCityPayElements: function () {
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

                    self.createElementsSession()
                        .then(function (session) {
                            self.session = session;
                            return loadCityPay();
                        })
                        .then(function (CityPay) {
                            self.citypay = CityPay.init({
                                merchantId: config.merchantId
                            });

                            return self.citypay({});
                        })
                        .then(function (api) {
                            return api.elements({
                                pubKey: config.pubKey,
                                createServerIntent: function () {
                                    return self.session;
                                },
                                eager: true
                            });
                        })
                        .then(function (elements) {
                            self.card = elements.cardElement({
                                identifier: 'default',
                                element: '#card-form',
                                layout: config.elementsStyle || 'row'
                            });

                            return self.card.init();
                        })
                        .then(function () {
                            return self.card.awaitReady();
                        })
                        .then(function () {
                            self.elementsLoading.resolve(self.card);
                        })
                        .fail(function (error) {
                            self.elementsLoading.reject(error);
                        });
                }, 0);

                return this.elementsLoading.promise();
            },

            selectPaymentMethod: function () {
                var result = this._super();

                if (this.isElementsMode()) {
                    this.initCityPayElements();
                }

                return result;
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
                                    console.log("Placing Order for ElementsPaymentManagement")
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