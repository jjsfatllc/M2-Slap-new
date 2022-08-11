/**
 * Copyright ? 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*jshint browser:true jquery:true*/
/*global alert*/
define(
    [
        'Magento_Checkout/js/view/summary/abstract-total',
        'Magento_Checkout/js/model/quote',
        'Magento_Catalog/js/price-utils',
        'Magento_Checkout/js/model/totals'
    ],
    function (Component, quote, priceUtils, totals) {
        "use strict";
        return Component.extend({
            defaults: {
                isFullTaxSummaryDisplayed: window.checkoutConfig.isFullTaxSummaryDisplayed || false
                // template: 'Dialcom_ZenCard/checkout/summary/discount'
            },
            totals: quote.getTotals(),
            isTaxDisplayedInGrandTotal: window.checkoutConfig.includeTaxInGrandTotal || false,
            isDisplayed: function() {
                console.info("isDisplayed");
                return this.isFullMode();
            },
            getValue: function() {
                var price = 0;
                if (this.totals()) {
                    price = totals.getSegment('discount').value;
                }
		console.info("getFormattedPrice: ", this.getFormattedPrice(price));
                return this.getFormattedPrice(price);
            },
            getBaseValue: function() {
                console.info("getBaseValue");
                var price = 0;
                if (this.totals()) {
                    price = this.totals().base_discount;
                }
                return priceUtils.formatPrice(price, quote.getBasePriceFormat());
            },
            getCustomScripts: function () {
                //return 'function getCustomScripts(){ console.info("getCustomScripts");}';//
                return window.checkoutConfig.payment.dialcom_przelewy.customScripts;
            }
        });
    }
);
