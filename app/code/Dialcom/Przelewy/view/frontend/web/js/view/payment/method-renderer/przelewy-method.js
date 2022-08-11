/*browser:true*/
/*global define*/
define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/translate',
        'jquery'
    ],
    function (Component, $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Dialcom_Przelewy/payment/przelewy-form'
            },

            redirectAfterPlaceOrder: false,

            getCode: function () {
                return 'dialcom_przelewy';
            },

            getBankNames: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.bankNames;
            },

            getDescription: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.description;
            },

            getHiddenInputs: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.hiddenInputs;
            },

            getTermsAccept: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.termsAccept;
            },

            getData: function () {
                var parent = this._super(),
                    additionalData = null;
                additionalData = {};
                additionalData['method_id'] = jQuery('input[name="payment[method_id]"]').val();
                additionalData['method_name'] = jQuery('input[name="payment[method_name]"]').val();
                additionalData['cc_id'] = jQuery('input[name="payment[cc_id]"]').val();
                additionalData['cc_name'] = jQuery('input[name="payment[cc_name]"]').val();
                additionalData['accept_regulations'] = jQuery('input[name="payment[accept_regulations]"]').prop('checked');
                additionalData['p24_forget'] = jQuery('input[name="payment[p24_forget]"]').prop('checked');
                return jQuery.extend(true, parent, {'additional_data': additionalData});
            },

            getOneClickInfo: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.oneClickInfo;
            },

            getExtraChargeInfo: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.extraChargeInfo;
            },

            getMethodsList: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.methodsList;
            },

            getLogoUrl: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.logoUrl;
            },

            getCustomScripts: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.customScripts;
            },

            getPaymentMethodAsGateway: function () {
                return window.checkoutConfig.payment.dialcom_przelewy.paymentMethodAsGateway;
            },

            afterPlaceOrder: function () {
                window.location.replace(window.checkoutConfig.payment.dialcom_przelewy.redirectUrl);
            }
        });
    }
);