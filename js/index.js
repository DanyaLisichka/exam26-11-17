document.addEventListener('DOMContentLoaded', function () {
    if (window.jQuery && jQuery.fn.inputmask) {
        jQuery('#validationCustomPhone').inputmask({ mask: '+7(999)999-99-99', showMaskOnHover: false });
    }
});
