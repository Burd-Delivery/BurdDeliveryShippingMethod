define(
    [],
    function () {
        'use strict';
        return {
            getRules: function() {
                return {
                    'postcode': {
                        'required': true
                    },
                    'country_id': {
                        'required': true
                    },
                    'city': {
                        'required': true
                    },
                    'telephone': {
                        'required': true
                    },
                    'username': {
                        'required': true
                    }
                };
            }
        };
    }
)