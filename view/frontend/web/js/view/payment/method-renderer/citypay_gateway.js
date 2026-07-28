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
                orderId: '',
                paymentChannel: 'card'
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
                        'orderId': this.orderId,
                        payment_intent_id: this.paymentIntentId,
                        payment_channel: this.paymentChannel || 'card'
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

                            return self.createElementsSession()
                                .then(function (session) {
                                    self.elementsSession = session;

                                    return citypay.elements({
                                        pubKey: pubKey,

                                        // Return the already-created session
                                        createServerIntent: function () {
                                            return Promise.resolve(session);
                                        },

                                        eager: true
                                    });
                                });
                        })
                        .then(function (elements) {
                            console.log("creating elements");
                            self.elements = elements;

                            self.card = elements.cardElement({
                                identifier: 'default',
                                element: '#card-form',
                                layout: elementsStyle || 'row'
                            });
                            console.log("Elements: init...");

                            const amount = Number(self.elementsSession.amount);
                            const GooglePayMerchantId = self.elementsSession.merchantId

                            if (!Number.isFinite(amount) || amount <= 0) {
                                throw new Error(
                                    'Invalid Apple Pay amount: ' + self.elementsSession.amount
                                );
                            }

                            const googlePayAmount = amount / 100;

                            self.applePay = elements.applePay({
                                identifier: 'applepay' + amount,
                                element: '#apple-pay',
                                appearance: {
                                    type: 'check-out',
                                    style: 'dark',
                                },
                                total: {
                                    amount: amount,
                                    // amount: 1,
                                    label: 'GBP'
                                }
                            });

                            self.googlePay = elements.googlePay({
                                element: '#google-pay',
                                identifier: 'quote-' + googlePayAmount,
                                environment: 'TEST', // use 'PRODUCTION' after Google approval
                                merchantId: GooglePayMerchantId,
                                // merchantName: 'Your Store',
                                channel: 'local',
                                total: {
                                    label: 'GBP',
                                    amount: googlePayAmount
                                },
                                appearance: {
                                    type: 'checkout',
                                    style: 'black',
                                    buttonSizeMode: 'fill'
                                },
                                emailAddressRequired: true,
                                billingAddressRequired: true,
                            });

                            self.applePay.onAuthoriseStart(function () {
                                // Prevent duplicate checkout submissions while Apple Pay is processing.
                                self.isPlaceOrderActionAllowed(false);
                            });

                            self.applePay.onAuthoriseEnd(function (event) {
                                if (!event.success) {
                                    // Allow the customer to try again.
                                    self.isPlaceOrderActionAllowed(true);
                                    return;
                                }

                                self.paymentChannel = 'apple_pay';

                                // Keep it false while Magento verifies CityPay and places the order.
                                self.getPlaceOrderDeferredObject()
                                    .done(function () {
                                        self.afterPlaceOrder();
                                        redirectOnSuccessAction.execute();
                                    })
                                    .fail(function () {
                                        self.isPlaceOrderActionAllowed(true);
                                        self.messageContainer.addErrorMessage({
                                            message: 'Your payment was received, but the order could not be created. Please contact support.'
                                        });
                                    });
                            });

                            self.googlePay.onTokeniseEnd(async function () {
                                self.isPlaceOrderActionAllowed(false);
                                self.paymentChannel = 'google_pay';

                                try {
                                    await self.googlePay.attach({intentId: self.paymentIntentId});

                                    const confirmResult = await self.googlePay.confirm({
                                        intentId: self.paymentIntentId
                                    });

                                    if (confirmResult.status !== 'requires_authorisation') {
                                        throw new Error('Unexpected Google Pay status: ' + confirmResult.status);
                                    }

                                    const auth = await self.authorisePayment(self.paymentIntentId);

                                    if (auth.authorised !== true && auth.authorised !== 'true') {
                                        throw new Error('Google Pay authorisation was declined.');
                                    }

                                    const verifyResult = await self.verify(self.paymentIntentId);

                                    const approved =
                                        verifyResult.status === 'success' ||
                                        verifyResult.authen_result === 'Y' ||
                                        verifyResult.result === 'Verified' ||
                                        verifyResult.trans_status === 'Verified';

                                    if (!approved) {
                                        throw new Error('Google Pay payment could not be verified.');
                                    }

                                    // Record the selected wallet before submitting the Magento order.
                                    self.paymentChannel = 'google_pay';

                                    await self.getPlaceOrderDeferredObject();

                                    self.afterPlaceOrder();

                                    if (self.redirectAfterPlaceOrder) {
                                        redirectOnSuccessAction.execute();
                                    }
                                } catch (error) {
                                    console.error('Google Pay failed:', error);

                                    self.messageContainer.addErrorMessage({
                                        message: error.message || 'Google Pay could not be completed.'
                                    });

                                    self.isPlaceOrderActionAllowed(true);
                                }
                            });

                            self.googlePay.onCancel(function () {
                                self.isPlaceOrderActionAllowed(true);

                                self.messageContainer.addErrorMessage({
                                    message: 'Google Pay was cancelled.'
                                });
                            });

                            self.googlePay.onError(function (error) {
                                console.error('Google Pay error:', error);

                                self.isPlaceOrderActionAllowed(true);

                                self.messageContainer.addErrorMessage({
                                    message: 'An error occurred while processing Google Pay.'
                                });
                            });

                            return self.card.init()
                                .then(function () {
                                    console.log("Apple Pay: init...");
                                    return self.applePay.init();
                                })
                                .then(function() {
                                    console.log('Google Pay: init...');
                                    return self.googlePay.init();
                                })
                                .then(function () {
                                    console.log("ApplePay, Google Pay, Elements: await...")
                                    return Promise.all([
                                        self.card.awaitReady(),
                                        self.applePay.awaitReady(),
                                        self.googlePay.awaitReady()
                                    ]);
                                }).then(function () {
                                    const walletGrid = document.querySelector(
                                        '#citypay-wallet-grid'
                                    );
                                    const applePayContainer = document.querySelector(
                                        '#apple-pay'
                                    );
                                    const applePayButton = applePayContainer
                                        ? applePayContainer.querySelector(
                                            'button.apple-pay-button'
                                        )
                                        : null;
                                    const googlePayContainer = document.querySelector(
                                        '#google-pay'
                                    );
                                    const googlePayButton = googlePayContainer
                                        ? googlePayContainer.querySelector('button.gpay-button')
                                        : null;
                                    const googlePayWrapper = googlePayButton
                                        ? googlePayButton.parentElement
                                        : null;

                                    if (walletGrid) {
                                        walletGrid.classList.add(
                                            'citypay-wallet-grid--ready'
                                        );
                                    }

                                    if (applePayButton) {
                                        applePayButton.style.setProperty(
                                            '-apple-pay-button-style',
                                            'black'
                                        );

                                        Object.assign(applePayButton.style, {
                                            display: 'block',
                                            width: '100%',
                                            maxWidth: 'none',
                                            height: '42px',
                                            minHeight: '42px',
                                            maxHeight: '42px',
                                            boxSizing: 'border-box',
                                            flex: '1 1 auto',
                                            margin: '0',
                                            padding: '0',
                                            border: '0'
                                        });
                                    }

                                    if (googlePayButton) {
                                        if (
                                            googlePayWrapper &&
                                            googlePayWrapper !== googlePayContainer
                                        ) {
                                            Object.assign(googlePayWrapper.style, {
                                                alignItems: 'stretch',
                                                width: '100%',
                                                maxWidth: 'none',
                                                height: '42px',
                                                margin: '0'
                                            });

                                            googlePayWrapper.style.setProperty(
                                                'display',
                                                'flex',
                                                'important'
                                            );
                                            googlePayWrapper.style.setProperty(
                                                'width',
                                                '100%',
                                                'important'
                                            );
                                            googlePayWrapper.style.setProperty(
                                                'max-width',
                                                'none',
                                                'important'
                                            );
                                        }

                                        Object.assign(googlePayButton.style, {
                                            display: 'block',
                                            width: '100%',
                                            maxWidth: 'none',
                                            height: '42px',
                                            minHeight: '42px',
                                            maxHeight: '42px',
                                            boxSizing: 'border-box',
                                            flex: '1 1 auto',
                                            margin: '0'
                                        });

                                        googlePayButton.style.setProperty(
                                            'width',
                                            '100%',
                                            'important'
                                        );
                                        googlePayButton.style.setProperty(
                                            'max-width',
                                            'none',
                                            'important'
                                        );
                                        googlePayButton.style.setProperty(
                                            'height',
                                            '42px',
                                            'important'
                                        );
                                    }
                                });
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
                    if (self.isElementsMode()) {
                        self.paymentChannel = 'card';
                    }

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
                                                console.log("CityPay:Elements: confirm result: ");

                                                if (confirmResult.status !== 'requires_authorisation') {
                                                    return;
                                                }

                                                return self.authorisePayment(self.paymentIntentId);
                                            }).then(function (auth) {
                                                console.log('Authorising result');
                                                return self.verify(self.paymentIntentId);
                                            }).then(function (verifyResult) {
                                                console.log("CityPay:Elements: verified result");

                                                const approved =
                                                    verifyResult.status === 'success' ||
                                                    verifyResult.authen_result === 'Y' ||
                                                    verifyResult.result === 'Verified' ||
                                                    verifyResult.trans_status === 'Verified';

                                                if (!approved) {
                                                    throw new Error('CityPay payment was not approved.');
                                                }

                                                self.messageContainer.addSuccessMessage({
                                                    message: 'Payment approved. Redirecting to your order confirmation.'
                                                });

                                                self.afterPlaceOrder();

                                                if (self.redirectAfterPlaceOrder) {
                                                    redirectOnSuccessAction.execute();
                                                }
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
