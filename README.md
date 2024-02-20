# Isotope Paytweak Interface
This bundle adds support for payment interface Paytweak to Isotope ecommerce bundle.

## Install
Use composer to install this bundle: `composer require webexmachina/contao-isotope-paytweak-bundle`

Or use the Contao Manager.

## Paytweak Documentation
Paytweak API documentation (english) can be found here: [https://api.paytweak.dev/doc/en/](https://api.paytweak.dev/doc/en/)
French version is here: [https://api.paytweak.dev/doc/fr/](https://api.paytweak.dev/doc/fr/)
There is no other languages available at the moment.

The bundle includes the PHP Wrapper provided by the documentation, since there is no dedicated repository for that.
Note that we added a namespace in order to use the library without having to use a include instruction.
Check usage section below.

## Bundle configuration
You just have to create a new Isotope Payment and fill the public and private keys provided by Paytweak. Since those keys are linked to a dev/prod account, there is no settings for the payment environment required.

## Bundle usage
This section explains how to use the bundle and more precisely the Paytweak features. If a feature, listed in Paytweak API, is not documented here, assume it is not included inside this bundle. You can request it with an issue in Github.

Note: This bundle filters Paytweak push requests to only deal with PAYMENT notice. Paytweak send also EMAIL requests, that we do not really care to validate an order.

### Test cards
You can find test payment cards here: [https://docs.axepta.bnpparibas/display/DOCBNP/Cartes+de+test+-+Autorisation](https://docs.axepta.bnpparibas/display/DOCBNP/Cartes+de+test+-+Autorisation).

## Possible enhancements
* Add a setting to automaticly redirect user to payment link once the checkout form is displayed
* Add a setting to display the QRCode, the link, or both
* Put Paytweak vars (status) in class constants to make it easier to understand 

## To discuss
* Should we checkout the order when the payment is invalid? Because it locks the order and user should recreate the same cart to retry an order in that case. But at the same time, we have the "definitive" order number to make it easier to identify payments.