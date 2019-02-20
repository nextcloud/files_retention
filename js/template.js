(function() {
  var template = Handlebars.template, templates = OCA.File_Retention.Templates = OCA.File_Retention.Templates || {};
templates['template'] = template({"compiler":[7,">= 4.0.0"],"main":function(container,depth0,helpers,partials,data) {
    var helper, alias1=depth0 != null ? depth0 : (container.nullContext || {}), alias2=helpers.helperMissing, alias3="function", alias4=container.escapeExpression;

  return "<tr data-id=\""
    + alias4(((helper = (helper = helpers.id || (depth0 != null ? depth0.id : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"id","hash":{},"data":data}) : helper)))
    + "\">\n	<td><span>"
    + alias4(((helper = (helper = helpers.tagName || (depth0 != null ? depth0.tagName : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"tagName","hash":{},"data":data}) : helper)))
    + "</span></td>\n	<td><span>"
    + alias4(((helper = (helper = helpers.timeAmount || (depth0 != null ? depth0.timeAmount : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"timeAmount","hash":{},"data":data}) : helper)))
    + "</span></td>\n	<td><span>"
    + alias4(((helper = (helper = helpers.timeUnit || (depth0 != null ? depth0.timeUnit : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"timeUnit","hash":{},"data":data}) : helper)))
    + "</span></td>\n	<td><a class=\"icon-delete has-tooltip\" title=\""
    + alias4(((helper = (helper = helpers.deleteString || (depth0 != null ? depth0.deleteString : depth0)) != null ? helper : alias2),(typeof helper === alias3 ? helper.call(alias1,{"name":"deleteString","hash":{},"data":data}) : helper)))
    + "\"></a></td>\n<tr>\n";
},"useData":true});
})();