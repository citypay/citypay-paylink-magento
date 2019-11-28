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
        'Magento_Checkout/js/action/redirect-on-success'
    ],
    function ($,Component, placeOrderAction,getplTokenAction,additionalValidators,redirectOnSuccessAction) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'CityPay_Paylink/payment/form',
                transactionResult: '',
                orderId:''
            },

            initObservable: function () {

                this._super()
                    .observe([
                        'transactionResult'
                    ]);
                return this;
            },

            getCode: function() {
                return 'sample_gateway';
            },

            getData: function() {
                return {
                    'method': this.item.method,
                    'additional_data': {
                        'transaction_result': this.transactionResult(),
                        'orderId':this.orderId
                    }
                };
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
                                self.orderId=value;
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
                return _.map(window.checkoutConfig.payment.sample_gateway.transactionResults, function(value, key) {
                    return {
                        'value': key,
                        'transaction_result': value
                    }
                });
            }
        });
    }
);