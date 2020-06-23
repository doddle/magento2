define([
    'jquery',
    'Magento_Ui/js/modal/alert'
], function ($, alert) {
    'use strict';

    $.widget('doddle.apiTest', {
        options: {
            successMessage: '',
            failMessage: '',
            buttonId: ''
        },
        _create: function () {
            this._on({
                'click': $.proxy(this._testApi, this)
            });
        },
        _testApi: function () {
            var apiKey = $('#doddle_returns_api_key').val();
            var apiSecret = $('#doddle_returns_api_secret').val();
            var basicString = btoa(apiKey + ':' + apiSecret);

            var apiMode = $('#doddle_returns_api_mode').val();
            var apiUrlSelector = apiMode == 'live' ? '#doddle_returns_api_live_url' : '#doddle_returns_api_test_url';
            var apiUrl = $(apiUrlSelector).val();

            var xhr = new XMLHttpRequest();
            xhr.onreadystatechange = function() {
                if (xhr.readyState == 4) {
                    if (xhr.status == 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.access_token) {
                                alert({content: this.options.successMessage});
                            } else {
                                alert({content: this.options.failMessage});
                            }
                        } catch(err) {
                            alert({content: this.options.failMessage});
                        }
                    } else {
                        alert({content: this.options.failMessage});
                    }
                }
            }.bind(this);

            xhr.open('POST', apiUrl + '/v1/oauth/token?api_key=' + apiKey, true);
            xhr.setRequestHeader('Authorization', 'Basic ' + basicString);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('grant_type=client_credentials');
        }
    });

    return $.doddle.apiTest;
});
