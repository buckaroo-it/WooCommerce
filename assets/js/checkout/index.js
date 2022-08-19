
import BuckarooCheckout from "./checkout";
import BuckarooValidateCreditCards from "./creditcard-call-encryption";
import BuckarooIdin from "./idin";
jQuery(function () {
  new BuckarooCheckout().listen();
  new BuckarooValidateCreditCards().listen();
  new BuckarooIdin().listen();
});
