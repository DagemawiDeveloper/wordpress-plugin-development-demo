(function ($) {
    'use strict';

    function showResult(message, isError) {
        $('#wpitk-test-result')
            .text(message)
            .toggleClass('is-error', !!isError)
            .toggleClass('is-success', !isError);
    }

    $('#wpitk-send-test').on('click', function () {
        var $button = $(this);
        var original = $button.text();
        $button.prop('disabled', true).text(wpitkAdmin.testing);
        showResult('', false);

        $.post(wpitkAdmin.ajaxUrl, {
            action: 'wpitk_send_test',
            nonce: wpitkAdmin.nonce
        }).done(function (response) {
            showResult(response.data.message, false);
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Webhook request failed.';
            showResult(message, true);
        }).always(function () {
            $button.prop('disabled', false).text(original);
        });
    });

    $('.wpitk-retry').on('click', function () {
        var $button = $(this);
        var original = $button.text();
        var logId = $button.data('log-id');
        $button.prop('disabled', true).text(wpitkAdmin.retrying);

        $.post(wpitkAdmin.ajaxUrl, {
            action: 'wpitk_retry',
            nonce: wpitkAdmin.nonce,
            log_id: logId
        }).done(function () {
            window.location.reload();
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : 'Retry failed.';
            window.alert(message);
            $button.prop('disabled', false).text(original);
        });
    });
}(jQuery));
