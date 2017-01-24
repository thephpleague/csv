$(function() {
    $('.versions').change(function (e) {
        location.href = $(this).find('option:selected').data('url');
    });
});