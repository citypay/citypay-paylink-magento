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
        'CityPay_Paylink/js/model/citypay-loader',
        'Magento_Checkout/js/model/quote',
    ],
    function (require, $,Component, placeOrderAction,getplTokenAction,additionalValidators,redirectOnSuccessAction,  urlBuilder, storage, loadCityPay, quote) {
        'use strict';

        const config = window.checkoutConfig.payment.citypay_gateway;
        const pubKey = config.pubKey;
        const elementsStyle = config.elementsStyle || 'row';

        return Component.extend({
            defaults: {
                template: 'CityPay_Paylink/payment/form',
                orderId: '',
                paymentChannel: 'card'
            },

            initObservable: function () {
                this._super();

                this.isChecked.subscribe(function () {
                    this.loadCityPayIfSelected();
                }, this);

                this.totalsSubscription = quote.totals.subscribe(
                    this.onQuoteTotalsChanged,
                    this
                );

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

            getInitialAmount: function (totals) {
                let amount;
                let currency;

                if (!totals) {
                    return null;
                }

                amount = Number(totals.grand_total);
                currency = String(totals.quote_currency_code || '').toUpperCase();

                if (!Number.isFinite(amount) || currency !== 'GBP') {
                    return null;
                }

                // Magento’s total is converted from pounds to pence
                return Math.round(amount * 100);
            },

            onQuoteTotalsChanged: function (totals) {
                let quoteAmount;
                let sessionAmount;

                // Avoid repeated reload attempts.
                if (this.reloadingForTotals) {
                    return;
                }

                // This logic applies only to Elements.
                if (!this.isElementsMode()) {
                    return;
                }

                // No CityPay session exists yet. When one is eventually created, the backend will use the latest Magento total.
                if (!this.elementsSession) {
                    return;
                }

                quoteAmount = this.getInitialAmount(totals);
                sessionAmount = Number(this.elementsSession.amount);

                if (quoteAmount === null || !Number.isFinite(sessionAmount)) {
                    return;
                }

                // The active intent still has the correct amount.
                if (quoteAmount === sessionAmount) {
                    return;
                }

                // The intent amount is now stale, usually because a coupon was applied or removed.
                this.reloadingForTotals = true;
                this.isPlaceOrderActionAllowed(false);

                window.location.reload();

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
                        paymentIntentId: paymentIntentId,
                        orderId: this.orderId,
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
                        paymentIntentId: paymentIntentId,
                        orderId: this.orderId,
                    })
                ).then(function (response) {
                    return typeof response === 'string' ? JSON.parse(response) : response;
                });
            },

            setWalletVisibility: function (containerId, visible) {
                const walletGrid = document.getElementById('citypay-wallet-grid');
                const walletContainer = document.getElementById(containerId);

                if (walletContainer) {
                    walletContainer.style.display = visible ? '' : 'none';
                    if (!visible) {
                        walletContainer.textContent = '';
                    }
                }

                if (visible && walletGrid) {
                    walletGrid.classList.add('citypay-wallet-grid--ready');
                }
            },

            waitForWalletFactory: function (elements, factoryName, initialiseWallet) {
                let attempts = 0;

                function tryInitialise() {
                    if (typeof elements[factoryName] === 'function') {
                        initialiseWallet();
                    } else if (++attempts < 100) {
                        window.setTimeout(tryInitialise, 100);
                    } else {
                        console.info('CityPay wallet is unavailable: ' + factoryName);
                    }
                }

                tryInitialise();
            },

            initOptionalWallets: function (elements) {
                const self = this;
                const amount = Number(this.elementsSession.amount);

                if (!Number.isFinite(amount) || amount <= 0) {
                    console.warn('CityPay wallets were not loaded because the amount is invalid.');
                    return;
                }

                this.waitForWalletFactory(elements, 'applePay', function () {
                    try {
                        self.applePay = elements.applePay({
                            identifier: 'applepay-' + amount,
                            element: '#apple-pay',
                            appearance: {type: 'check-out', style: 'dark'},
                            total: {amount: amount, label: 'GBP'}
                        });

                        self.applePay.onAuthoriseStart(function () {
                            self.isPlaceOrderActionAllowed(false);

                            return self.placePendingElementsOrder('apple_pay');
                        });
                        self.applePay.onAuthoriseEnd(async function (event) {
                            if (!event.success) {
                                self.isPlaceOrderActionAllowed(true);
                                return;
                            }

                            try {
                                self.orderId = await self.placePendingElementsOrder(
                                    'apple_pay'
                                );

                                const verifyResult = await self.verify(
                                    self.paymentIntentId
                                );

                                if (verifyResult.approved !== true) {
                                    throw new Error('Apple Pay payment could not be verified.');
                                }

                                self.afterPlaceOrder();

                                if (self.redirectAfterPlaceOrder) {
                                    redirectOnSuccessAction.execute();
                                }
                            } catch (error) {
                                self.messageContainer.addErrorMessage({
                                    message:
                                        error.message ||
                                        'Apple Pay could not be completed.'
                                });

                                self.isPlaceOrderActionAllowed(true);
                            }
                        });

                        Promise.resolve(self.applePay.init())
                            .then(function () { return self.applePay.awaitReady(); })
                            .then(function () { self.setWalletVisibility('apple-pay', true); })
                            .catch(function (error) {
                                self.setWalletVisibility('apple-pay', false);
                                console.info('Apple Pay is unavailable:', error);
                            });
                    } catch (error) {
                        self.setWalletVisibility('apple-pay', false);
                        console.info('Apple Pay could not be initialised:', error);
                    }
                });

                this.waitForWalletFactory(elements, 'googlePay', function () {
                    try {
                        const googlePayAmount = amount / 100;

                        self.googlePay = elements.googlePay({
                            element: '#google-pay',
                            identifier: 'googlePay-' + googlePayAmount,
                            merchantId: self.elementsSession.googlepayMID,
                            total: {label: 'GBP', amount: googlePayAmount},
                            appearance: {type: 'checkout', style: 'black', buttonSizeMode: 'fill'},
                            emailAddressRequired: true,
                            billingAddressRequired: true
                        });

                        self.googlePay.onTokeniseEnd(async function () {
                            self.isPlaceOrderActionAllowed(false);

                            try {
                                // Create Magento order first and retain its ID.
                                self.orderId = await self.placePendingElementsOrder(
                                    'google_pay'
                                );

                                const attach = await self.googlePay.attach({
                                    intentId: self.paymentIntentId
                                });

                                if (attach.status !== 'requires_customer_confirmation') {
                                    throw new Error('Unexpected Google Pay attach status: ' + attach.status);
                                }

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
                                if (verifyResult.approved !== true) {
                                    throw new Error('Google Pay payment could not be verified.');
                                }

                                self.afterPlaceOrder();

                                if (self.redirectAfterPlaceOrder) {
                                    redirectOnSuccessAction.execute();
                                }

                            } catch (error) {
                                self.messageContainer.addErrorMessage({
                                    message: error.message || 'Google Pay could not be completed.'
                                });
                                self.isPlaceOrderActionAllowed(true);
                            }
                        });
                        self.googlePay.onCancel(function () {
                            self.isPlaceOrderActionAllowed(true);
                            self.messageContainer.addErrorMessage({message: 'Payment cancelled by user'});
                        });
                        self.googlePay.onError(function () {
                            self.isPlaceOrderActionAllowed(true);
                            self.messageContainer.addErrorMessage({
                                message: 'An error occurred while processing Google Pay.'
                            });
                        });

                        Promise.resolve(self.googlePay.init())
                            .then(function () { return self.googlePay.awaitReady(); })
                            .then(function () { self.setWalletVisibility('google-pay', true); })
                            .catch(function (error) {
                                self.setWalletVisibility('google-pay', false);
                                console.info('Google Pay is unavailable:', error);
                            });
                    } catch (error) {
                        self.setWalletVisibility('google-pay', false);
                        console.info('Google Pay could not be initialised:', error);
                    }
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

                            return self.card.init()
                                .then(function () {
                                    return self.card.awaitReady();
                                })
                                .then(function () {
                                    self.initOptionalWallets(elements);
                                    return self.card;
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
                    (self.isElementsMode()
                        ? self.placePendingElementsOrder('card')
                        : self.getPlaceOrderDeferredObject())
                        .then(
                            function (value) {
                                self.orderId = value;

                                if (self.isPaylinkMode()) {
                                    console.log("Placing order for Paylink")
                                    self.getPLTokenDeferredObject();
                                } else if (self.isElementsMode()) {
                                    console.log("Placing Order for ElementsPaymentManagement");

                                    return self.card.tokenise()
                                        .then(function (tokeniseResponse) {
                                            const token = tokeniseResponse.data.cp_card_token;

                                            return self.card.attach({
                                                intentId: self.paymentIntentId,
                                                token: token
                                            });
                                        })
                                        .then(function () {
                                            return self.card.confirm({
                                                intentId: self.paymentIntentId,
                                            });
                                            }).then(function (confirmResult) {
                                                console.log("CityPay:Elements: confirm result: ");

                                                if (confirmResult.status !== 'requires_authorisation') {
                                                    return;
                                                }

                                                return self.authorisePayment(self.paymentIntentId);
                                            }).then(function (authResult) {
                                                console.log('Authorising result');
                                                if (authResult.authorised !== true && authResult.authorised !== 'true') {
                                                    throw new Error('CityPay authorisation was declined.');
                                                }
                                                return self.verify(self.paymentIntentId);

                                            }).then(function (verifyResult) {
                                                console.log("CityPay:Elements: verified result");

                                                if (verifyResult.approved !== true) {
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
                                }
                            }
                        )
                        .fail(function (error) {

                            self.messageContainer.addErrorMessage({
                                message: error.message || 'The card payment could not be completed.'
                            });
                        })
                        .always(
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
            placePendingElementsOrder: function (paymentChannel) {
                const self = this;

                if (this.orderId) {
                    return $.Deferred().resolve(this.orderId).promise();
                }

                if (this.pendingElementsOrder) {
                    return this.pendingElementsOrder.promise();
                }

                // getData() is evaluated by placeOrderAction, so record the
                // actual Elements channel before submitting the Magento order.
                this.paymentChannel = paymentChannel;
                this.pendingElementsOrder = $.Deferred();

                this.getPlaceOrderDeferredObject()
                    .done(function (orderId) {
                        self.orderId = orderId;
                        self.pendingElementsOrder.resolve(orderId);
                    })
                    .fail(function (error) {
                        // Keep the rejected promise for this page lifecycle.
                        // Retrying implicitly from a later wallet callback could create an order only after funds were taken.
                        self.pendingElementsOrder.reject(error);
                    });

                return this.pendingElementsOrder.promise();
            },
            getPLTokenDeferredObject:function(){
                return $.when(
                    //alert('$ when2'),
                    getplTokenAction(this.getData(),this.messageContainer)
                );
            },
        });
    }
);
