"use strict";
var prime   = require('prime'),
    Section = require('./section'),

    getAjaxURL = require('../../utils/get-ajax-url').config;

var Offcanvas = new prime({
    inherits: Section,
    options: {
        type: 'offcanvas',
        attributes: {
            name: "Offcanvas Section"
        }
    },

    layout: function() {
        var settings_uri = getAjaxURL(this.getPageId() + '/layout/' + this.getType() + '/' + this.getId());
        return '<div class="offcanvas-section" data-lm-id="' + this.getId() + '" data-lm-blocktype="' + this.getType() + '"><div class="section-header clearfix"><h4 class="float-left">' + (this.getAttribute('name')) + '</h4><div class="section-actions float-right"><span data-tip="Adds a new row in the offcanvas" data-tip-place="top-left"><i class="fa fa-plus"></i></span> <span data-tip="Offcanvas settings" data-tip-place="top-left"><i aria-label="Configure Offcanvas Settings" class="fa fa-cog" data-lm-settings="' + settings_uri + '"></i></span></div></div></div>';
    },

    getId: function() {
        return this.id || (this.id = this.options.type);
    }
});

module.exports = Offcanvas;
