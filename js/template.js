(function() {
  var template = Handlebars.template, templates = OCA.File_Retention.Templates = OCA.File_Retention.Templates || {};
templates['template'] = template({"compiler":[8,">= 4.3.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=container.hooks.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<tr data-id=\""
    + alias4(((helper = (helper = helpers.id || (depth0 != null ? depth0.id : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"id","hash":{},"data":data,"loc":{"start":{"line":1,"column":13},"end":{"line":1,"column":19}}}) : helper)))
    + "\">\n	<td><span>"
    + alias4(((helper = (helper = helpers.tagName || (depth0 != null ? depth0.tagName : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"tagName","hash":{},"data":data,"loc":{"start":{"line":2,"column":11},"end":{"line":2,"column":22}}}) : helper)))
    + "</span></td>\n	<td><span>"
    + alias4(((helper = (helper = helpers.timeAmount || (depth0 != null ? depth0.timeAmount : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"timeAmount","hash":{},"data":data,"loc":{"start":{"line":3,"column":11},"end":{"line":3,"column":25}}}) : helper)))
    + "</span></td>\n	<td><span>"
    + alias4(((helper = (helper = helpers.timeUnit || (depth0 != null ? depth0.timeUnit : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"timeUnit","hash":{},"data":data,"loc":{"start":{"line":4,"column":11},"end":{"line":4,"column":23}}}) : helper)))
    + "</span></td>\n	<td><a class=\"icon-delete has-tooltip\" title=\""
    + alias4(((helper = (helper = helpers.deleteString || (depth0 != null ? depth0.deleteString : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"deleteString","hash":{},"data":data,"loc":{"start":{"line":5,"column":47},"end":{"line":5,"column":63}}}) : helper)))
    + "\"></a></td>\n<tr>\n";
},"useData":true});
})();