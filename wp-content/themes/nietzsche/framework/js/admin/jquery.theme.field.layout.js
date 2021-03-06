(function ($) {
	"use strict";
	/*jslint undef: false, browser: true, devel: false, eqeqeq: false, bitwise: false, white: false, plusplus: false, regexp: false, nomen: false */
	/*jshint undef: false, browser: true, devel: false, eqeqeq: false, bitwise: false, white: false, plusplus: false, regexp: false, nomen: false, validthis: true */
	/*global jQuery,setTimeout,location,setInterval,YT,clearInterval,clearTimeout,pixelentity,JSON,ajaxurl, */
	
	$.pixelentity = $.pixelentity || {version: '1.0.0'};
	
	$.pixelentity.peFieldLayout = {	
		conf: {
			api: false
		},
		modules: {},
		addModule: function(name,module,proto) {
			var m = $.pixelentity.peFieldLayout.modules; 
			if (name === "Standard") {
				m.Standard = module;
			} else {
				$.extend(
					module.prototype,
					m.Standard.prototype,
					proto
				);
				
				m[name] = module;
			}
		}
	};
	
	var modules = $.pixelentity.peFieldLayout.modules;
	var headTemplate = '<div class="head"><i class="drag"></i><h3><span class="title">%0</span><span class="type">%1</span><i class="collapse"></i></h3><i class="clone %2"></i><i class="delete"></i></div>';
	var bodyTemplate = '<div class="config"></div>';
	var itemTemplate = '<li class="pe_block"><input name="type" type="hidden" value="%0" /><input name="status" type="hidden" value="" /><input name="config" type="hidden" value="" /><input name="bid" type="hidden" value="%3" /><div>%1%2</div></li>';
	
	
	function PeFieldLayout(target, conf) {
		
		var sortablesID = 0;
		var buttons;
		var blockID = 0;
		var chooser;
		var layout;
		var current;
		var lastplus;
		var animating = false;
		var checked;
		var rootFilter = false;
		var form,formaction;
		var errorChecked = false;
		var clicked;
		var preview;
		
		function makeSortable(t,group) {
			sortablesID++;
			
			var list = t.find("ul.pe_block_container");
			list.attr("id","%0_sortable_%1".format(target.attr("id"),sortablesID));
			list.data("group",group);
			
			list.sortable({
				axis:"y",
				//handle: "i.drag",
				handle: "div.head",
				delay: 50,
				tolerance: "pointer",
				items: '> li.pe_block:not(.pe_disabled)',
				//items: '> li.pe_block:not(.has_items)',
				cursor: 'move',
				distance: 0,
				scroll: true,
				//helper: 'clone',
				helper: sortableClone,
				appendTo: "#"+target.attr("id")+' .pe_layout_builder .pe_block_container',
				containment: '#'+target.attr("id"),
				cancel: "a,input,button,select,textarea",
				placeholder: "pe_block ui-state-highlight",
				forcePlaceholderSize: true,
				forceHelperSize: false,
				toleranceElement: '> div',
				disableSelection: true,
				dropOnEmtpy:true,
				zIndex: 9999
				//connectWith:"#list1,#list2,#list3",
			}).css("overflow:auto");
			
			linkSortables();
		}
		
		function linkSortables() {
			
			var sortables = [],list = target.find("ul.pe_block_container");
			var i = 0,s,groups = {},g,id;
			
			// create groups (ids list)
			for (i=0;i<list.length;i++) {
				sortables.push(s = list.filter(list[i]));
				g = s.data("group");
				id = "#"+s.attr("id");
				groups[g] = (typeof groups[g] == 'undefined') ? id : (groups[g] + "," + id);
			}
			
			// link sortables
			for (i=0;i<sortables.length;i++) {
				s = sortables[i];
				s.sortable( "option", "connectWith",groups[s.data("group")]);
			}
		}
		
		function toggleCollapse(e) {
			var el = target.find(e.currentTarget).closest("li.pe_block"); 
			var state = el.hasClass("collapsed") ? "open" : "collapsed";
			el.find('input[name="status"]:first').val(state);
			
			if (state === "collapsed") {
				el.addClass("collapsed");
				el.data("controller").title();
			} else {
				el.removeClass("collapsed");
				el.data("controller").focus();
			}
			
		}
		
		function clone(el) {
			var controller = el.data("controller");
			if (controller.conf.unique === '1') {
				return false;
			}
			var api = controller.api;
			var name,data = {};
			
			for (name in api) {
				if (api[name] && typeof api[name].getData === 'function') {
					data[name] = api[name].getData();
				}
			}
			
			var conf = {
					//status: "collapsed",
					type: el.find("> input[name=type]").val(),
					config: el.find("> input[name=config]").val(),
					status: el.find("> input[name=status]").val(),
					data: data
				};
			
			var items = controller.items;
			if (items && items.length > 0) {
				var child;
				conf.items = [];
				items.children().each(function () {
					child = clone(items.find(this));
					if (child) {
						conf.items.push(child);
					}
				});
			}
			
			return conf;
		}
		
		function cloneBlockHandler(e) {
			var el = target.find(e.currentTarget).closest("li.pe_block");
			
			var conf = clone(el);
			
			if (clone) {
				addBlock(clone(el),el,"after");
				//el.find(".head h3").trigger("click");
			}
			
			return false;
		}
		
		function deleteBlock(e) {
			var el = target.find(e.currentTarget).closest("li.pe_block");
			
			el.find("> div > .config > ul.pe_block_container > li .head i.delete").trigger("click");
			el.data("controller").remove();
			
			if (el.data("controller").conf.unique === "1") {
				var type = el.data("controller").type;
				var parent = el.parent();
				var uniques = parent.data("uniques");
				uniques[type] = false;
				parent.data("uniques",uniques);
			}
			
			el.data("controller","");
			el.remove();
			
			return false;
		}
		
		function clearCSSAnimation() {
			this.css("opacity",1).removeClass("pe_hilight");
			this.data("controller").focus();
			animating = false;
		}
		
		function addBlock(type,where,fun,animate) {
			
			var items = false,data = false,status,isNew = true,config = false;
			var uniques = where.data("uniques");
			if (!uniques) {
				uniques = {};
			}
			
			fun = fun ? fun : "append";
			
			var bid = false;
			
			if (typeof type !== "string") {
				items = type.items;
				data = type.data;
				status = type.status;
				bid = type.bid;
				config = type.config;
				type = type.type;
				isNew = false;
			}
 
			if (bid) {
				bid = parseInt(bid,10);
				blockID = Math.max(bid,blockID);
			} else {
				bid = ++blockID;
			}
			
			var conf = window['pe_theme_layout_module_%0'.format(type)];
			if (!conf) {
				// if no configuration present it means this layout module class is no longer included in theme
				return;
			}
			
			if (conf.unique === "1" && uniques[type]) {
				// this block type can only be added once and it already was
				return;
			}
			
			if (conf.prepend === "1") {
				fun = "prepend";
			}
			
			//var cls = (modules[type]) ? type : "Standard";
			var module = new modules[conf.jsclass](self,conf); 
			module.id = bid;
			module.type = type;
			
			if (module.setup) {
				module.setup(self,conf);
			}
			
			var template = $(itemTemplate.format(
					type,
					headTemplate.format(conf.messages.title,conf.messages.type,conf.unique === '1' ? 'disabled' : ''),
					bodyTemplate,
					bid
				));
			
			if (conf.sortable === "0") {
				template.addClass("pe_disabled");
			}
			
			template.data("controller",module);
			
			var body = template.find("div.config");
			var wrapper = $('<div class="pe_block_settings"></div>');
						
			var field,i;
			
			for (i=0;i<conf.fields.length;i++) {
				field = conf.templates[conf.fields[i]];
				wrapper.append(getItemField(field,module.id));
			}
			
			if (conf.fields.length > 0) {
				wrapper.append('<div class="pe_handle"></div>');
				if (!data || config == "open") {
					config = "open";
					wrapper.addClass("pe_active");
				}
				body.append(wrapper);
			}
			
			if (module.container) {
				template.addClass("has_items");
				body.append('<ul class="pe_block_container" data-type="%0"></ul>'.format(type));
				body.append('<div class="pe_block_plus"></div>');
				//body.append('<div class="pe_block_clone"></div>');
			} else {
				//body.append('<div class="pe_block_clone"></div>');
			}
			
			template = module.template ? module.template(template) : template;
			
			where[fun](template);
			
			populate(module,data);
			
			if (module.container) {
				makeSortable(template,module.sortable);
			}
			
			if (module.init) {
				module.init(data,isNew);
			}
			
			if (isNew && module.container) {
				var firstContainer = body.find("> ul.pe_block_container:first");
				
				if (firstContainer.find("> li").length === 0) {
					//var msg = '<div class="pe-arrow-down"></div>Use <i class="pe_icon_plus"></i> below to add more blocks to this %0'.format(module.conf.messages.type);
					var msg = '';
					var custom = '';
					if (module.conf.messages && module.conf.messages.help) {
						custom = module.conf.messages.help;
					}
					
					try {
						if (module.conf.fields.length > 0) {
							//msg += ' - Use <i class="pe_icon_edit"></i> to show/hide %0 settings'.format(module.conf.messages.type);
							msg = '<div class="pe-help-container pe-up"><div class="pe-arrow-up"></div><div class="pe-help-message">Click to show or hide %0 settings</div></div>'.format(module.conf.messages.type);
						}
					} catch (x) {
					}
					custom = custom ? custom : 'Click to add more blocks to this %0'.format(module.conf.messages.type);
					msg += '<div class="pe-help-container pe-down"><div class="pe-help-message"></div>%0<div class="pe-arrow-down"></div></div>'.format(custom);

					firstContainer.append('<li class="pe_help">%0</li>'.format(msg));	
				}				
			}
		
			$.pixelentity.tooltip(template);
			
			if (status == "collapsed") {
				template.addClass("collapsed");
			}
			
			if (conf.conditions) {
				template.find('.config .pe_block_settings').peMetaboxConditional({
					id: 'instance_%0'.format(module.id),
					options:conf.conditions
				});
			}
			
			template.find('input[name="status"]:first').val(status == "collapsed" ? "collapsed" : "open");
			template.find('input[name="config"]:first').val(config == "open" ? "open" : "collapsed");
			
			if (!animating && (animate || (fun != "append" && conf.prepend != "1") || chooser.parent().length > 0)) {
				animating = true;
				template.css("opacity",0);
				setTimeout(function () {
					fadeIn(template);
				},20);
			}
			
			if (items) {
				for(i=0;i<items.length;i++) {
					addBlock(items[i],module.target.find("ul.pe_block_container:eq(0)"));
				}
			}
			
			if (conf.unique === "1") {
				uniques[type] = true;
				where.data("uniques",uniques);
			}
			
			return module;
		}
		
		function fadeIn(template) {
			template.peScrollVisible(true);
			template.addClass("pe_hilight");
			template.one('oanimationend animationend webkitAnimationEnd', $.proxy(clearCSSAnimation,template));
		}

		
		function toggleChooser() {
			var active = false;
			var main = current.is(buttons.prev());
			
			if (lastplus) {
				lastplus.removeClass("pe_close");
			}
			
			if (current.is(chooser.parent())) {
				chooser.detach();
			} else {
				active = true;
				current.append(chooser);
				
				var uniques = current.data("uniques");
				if (!uniques) {
					uniques = {};
				}
				
				var filter,module = current.closest(".pe_block").data("controller");
				var all = chooser.removeClass("pe_filter").find("> div > div").removeClass("pe_active");
				
				if (module && module.container && (filter = module.filter())) {
					chooser.addClass("pe_filter");
					all.filter(filter).addClass("pe_active");
				} else if (!module && rootFilter) {
					chooser.addClass("pe_filter");
					all.filter(rootFilter).addClass("pe_active");
				}
				
				if (uniques) {
					var u;
					for (u in uniques) {
						if (uniques[u]) {
							all.filter("#pe_module_%0".format(u)).removeClass("pe_active");
						}
					}
				}
				
				current.next().peScrollVisible(true);
			}
			
			if (active && !main) {
				lastplus = current.next().addClass("pe_close");
			}
			
			var btn = buttons.find(".addblock");
			btn.val(main && active ? btn.attr("data-cancel") : btn.attr("data-add"));
			
			//chooser.toggleClass("pe_active");
		}

		
		function addBlockHandler(e) {
			
			var btn = target.find(e.currentTarget);
			current = btn.prev();
			current = current.is("ul") ? current : btn.parent().prev();
			
			var module = current.closest(".pe_block").data("controller");
			
			if (current) {
				current.find("> li.pe_help").remove();
			}
			
			if (!module || !module.add || !module.add()) {
				toggleChooser();
			} 
			
			return false;
		}
		
		function modulesHandler(e) {
			var m = addBlock(e.currentTarget.id.replace("pe_module_",""),current);
			toggleChooser();
			if (m) {
				m.focus();
			}
			//console.log(m.target);
		}
		
		function previewHandler() {
			//ajaxSaveRevision();
			if (preview.hasClass("pe-enabled")) {
				preview.removeAttr("src").removeClass("pe-enabled");
			} else {
				form.attr("target",target.attr("id")+"_preview");
				//console.log(form.attr)
				preview.attr("src",$("#preview-action a[href]").attr("href")).addClass("pe-enabled");
				var url=$("#preview-action a[href]").attr("href");				
				ajaxSaveRevision(url);
			}
		}

		
		function sortableClone(e,el) {
			var input,inputs = el.find('input[type=radio],input[type=checkbox]');
			var cl = el.clone();
			checked = [];
			inputs.each(function (i) {
				input = inputs.eq(i);
				if (input.is(":checked")) {
					checked.push("#%0".format(input.attr("id")));
					//console.log("here");
					//console.log(cl.find("#%0".format(input.attr("id"))).is(":checked"));
				}
			});
			return cl;
		}

		
		function sortStart(e,ui) {
			var i = ui.item.find('input[type=radio]');
			var h = ui.helper.addClass("collapsed").height("auto").width(target.width()-2).height();
			ui.placeholder.addClass("collapsed").height(h);
		}
		
		function sortOver(e,ui) {
			ui.placeholder.parent().addClass("sorting").find("> li:not(.has_items)").addClass("collapsed").end().find("> .pe_help").remove();
		}
		
		function sortStop(e,ui) {
			if (checked) {
				ui.item.find(checked.join(",")).prop("checked",true);
			}
			target.find("ul.sorting").removeClass("sorting");
		}
		
		function editor() {
			var textarea  = target.find(this).next("textarea");
			window.peThemeCustomEditor.show(textarea.attr("id"));
			return false;
		}
		
		function getFields(t) {
			var f = "input,textarea,select";
			var fields = t.find("> div > .config").children().not("ul");
			fields = fields.filter(f).add(fields.find(f));
			// remove button fields
			fields = fields.not("[type='button']");
			// only fields with "name" set
			fields = fields.filter("[name]");
			//console.log(fields);
			return fields;
		}
		
		
		function save(t,prefix,index) {
			
			t.find("[name]").each(function (idx) {
				var el = t.find(this);
				if (el.data("pe-builder-name")) {
					el.attr("name",el.data("pe-builder-name"));
				} else {
					el.data("pe-builder-name",el.attr("name"));
				}
			});
						
			return saveItem(t,prefix,index);
		}
		
		function saveItem(t,prefix,index) {
			var inputs = false;
			var i,f,name,fidx;
			
			prefix = prefix || "data";
			index = index || 0;
			
			if (!t.is("ul")) {
				prefix = prefix+"[%0]".format(index);
				var fields = getFields(t);
				
				if (fields.length > 0) {
					//inputs = {};
					inputs = [];
					for (i=0;i<fields.length;i++) {
						f = fields.filter(fields[i]);
						name = f.attr("name").replace(/instance_\d+_/,"");
						//if ((fidx = name.match(/\[\d*\]/))) {
						if ((fidx = name.match(/\[.*\]/))) {
							fidx = fidx[0];
							name = name.replace(fidx,"");
						} else {
							fidx = "";
						}
						//inputs[name] = f.val();
						//f.attr("name",'%0[data][%1]%2'.format(prefix,name,fidx.replace(/\d+/,"")));
						f.attr("name",'%0[data][%1]%2'.format(prefix,name,fidx.replace(/\[\d+\]$/,"[]")));
						if (!f.is(":radio") || f.is(":checked")) {
							inputs.push({
								name: f.attr("name"),
								value: f.val()
							});
						}
						//console.log(name,f.attr("name"));
					}
				}
				
				
			}
			
			var data = {
					inputs: inputs
				};
			
			if (t.is("li")) {
				var type = t.find("> input[name='type']");
				data.type = type.val();
				type.attr("name",'%0[type]'.format(prefix));
				var status = t.find("> input[name='status']");
				data.status = status.val();
				status.attr("name",'%0[status]'.format(prefix));
				var config = t.find("> input[name='config']");
				data.config = config.val();
				config.attr("name",'%0[config]'.format(prefix));
				var bid = t.find("> input[name='bid']");
				data.bid = bid.val();
				bid.attr("name",'%0[bid]'.format(prefix));
			}
			
			var isUL = t.is("ul");
			
			if (isUL || t.hasClass("has_items")) {
				
				var items = [],c,childs = isUL ? t.find("> li") : t.find("> div > .config > ul.pe_block_container > li");
				
				for(i = 0;i<childs.length;i++) {
					items.push(saveItem(target.find(childs[i]),'%0[items]'.format(prefix),i));
				}
				
				if (items.length > 0) {
					data.items = items;
				}
			}
			
			 
			return data;
		}
		
		function load() {
			var i,items,data = conf.data || target.attr("data-blocks");
			if (data && (items = data.items)) {
				for (i=0;i<items.length;i++) {
					addBlock(items[i],target.find(".pe_layout_builder > ul.pe_block_container"));
				}
			}
		}
		
		function resetButtonsStyle() {
			$(':button, :submit', '#submitpost').each(function(){
                var t = $(this);
                if ( t.hasClass('button-primary') )
                    t.removeClass('button-primary-disabled');
                else
                    t.removeClass('button-disabled');
            });
            if (clicked.id == 'publish' )
                $('#major-publishing-actions .spinner').hide();
            else
                $('#minor-publishing .spinner').hide();
		}

		function ajaxSaveSucces(data) {
			var frame = preview.contents()[0];  
			frame.open();
            frame.write(data);
            frame.close();
			preview.addClass("pe-enabled");
		}
		
		function ajaxSaveRevision(url) {
			save(target.find(".pe_layout_builder > ul"),target.attr("data-prefix"),0);
			
			form.find('input#wp-preview').val('dopreview');
			preview.addClass("pe-enabled");
			form.attr('target', '%0_preview'.format(target.attr("id"))).submit().attr('target', '');	
			/*
			 * Workaround for WebKit bug preventing a form submitting twice to the same action.
			 * https://bugs.webkit.org/show_bug.cgi?id=28633
			 */
			var ua = navigator.userAgent.toLowerCase();
			if ( ua.indexOf('safari') != -1 && ua.indexOf('chrome') == -1 ) {
				$('form#post').attr('action', function(index, value) {
					return value + '?t=' + new Date().getTime();
				});
			}

			$('input#wp-preview').val('');
			
			/*
			save(target.find(".pe_layout_builder > ul"),target.attr("data-prefix"),0);
			formaction.data("origaction",formaction.val()).val("pe_theme_builder_save_revision");
			$.ajax({
                type: 'POST',
                url: url || ajaxurl,
                data: form.serialize(),
                success: ajaxSaveSucces
            });
			formaction.val(formaction.data("origaction"));
			*/
		}
		
		function publish(e) {
			chooser.detach();
			target.find('li.pe_help').remove();
			clicked = e.currentTarget;
			
			try {
				save(target.find(".pe_layout_builder > ul"),target.attr("data-prefix"),0);
				return true;
			} catch (x) {
				return false;
			}
		}
		
		function showSettings(e) {
			var el = target.find(e.currentTarget).parent().toggleClass("pe_active");
			var block = el.closest("li.pe_block");
			block.find('input[name="config"]:first').val(el.hasClass("pe_active") ? "open" : "collapsed");
		}
		
		function hideSettings(e) {
			target.find(e.currentTarget).parent().find("> div.pe_block_settings").removeClass("pe_active");
		}
		
		function autosave(event,jqxhr,settings) {
			if (settings && settings.data && settings.data.match(/action=autosave/)) {
				// add builder data to the autosave
				save(target.find(".pe_layout_builder > ul"),target.attr("data-prefix"),0);
				settings.data += '&%0'.format(target.find("input,textarea,select").serialize());
			}
		}
		
		// init function
		function start() {
			
			preview = $('<iframe class="pe-builder-iframe-preview" id="%0_preview"></iframe>'.format(target.attr("id")));
			//preview.attr("src","http://oneup.themes.lan/empty/");
			$("body").prepend(preview);
			
			makeSortable(target,"block");
			form = target.find(".pe_layout_builder > ul").closest("form");
			
			// test this: save builder values if user clicks enter in a text field triggering the form autosave
			form.on("submit",function () {
				save(target.find(".pe_layout_builder > ul"),target.attr("data-prefix"),0);
			});
			
			formaction = form.find("[name=action]");
			buttons = target.find("div.buttons");
			chooser = target.find(".pe_layout_modules").detach();
			layout = target.find(".pe_layout_builder > .pe_block_container");
			
			if (target.attr("data-allowed")) {
				rootFilter = ".group_"+target.attr("data-allowed");
			}
			
			target.on("click",".buttons input.addblock, .pe_block.has_items div.pe_block_plus",addBlockHandler);
			//target.on("click",".pe_block div.pe_block_clone",cloneBlockHandler);
			target.on("click",".pe_block .head i.delete",deleteBlock);
			target.on("click",".pe_block .head i.clone",cloneBlockHandler);
			target.on("click",".pe_block .head h3",toggleCollapse);
			target.on("click","a.editor",editor);
			target.on("sortstart","ul.pe_block_container",sortStart);
			target.on("sortover","ul.pe_block_container",sortOver);
			target.on("sortstop","ul.pe_block_container",sortStop);
			target.on("click",".config > div.pe_block_settings > div.pe_handle",showSettings);
			target.on("click",".pe_layout_builder .pe_layout_modules > div > div.pe_module",modulesHandler);
			target.on("click",".pe-preview .button",previewHandler);
			$("#publish,#save-post,#post-preview").click(publish);
			load();
			
			target.addClass('pe-active');
			//publish();
			
			// testing
			/*
			buttons.find("select").val("tabs");
			buttons.find(".addblock").trigger("click");
			buttons.find("select").val("content");
			buttons.find(".addblock").trigger("click");
			*/
			//
		}
		
		function populate(block,data) {
			
			var i = 0,fidx,field,name,fields;
			
			// old method (set fields)
			/*
			if (data) {
				fields = getFields(block.target);
				
				for (;i<fields.length;i++) {
					field = fields.filter(fields[i]);
					name = field.attr("name").replace(/instance_\d+_/,"");
					if (typeof data[name] === "undefined") {
						continue;
					}
					if ((fidx = name.match(/\[\d*\]/))) {
						fidx = fidx[0];
						name = name.replace(fidx,"");
					} else {
						fidx = "";
					}
					
					if (field.is(":checkbox") || field.is(":radio")) {
						if (typeof data[name] === "object") {
							field.prop("checked",$.inArray(field.val(),data[name]) != -1);
						} else {
							field.prop("checked",field.val() == data[name]);
						}
						//console.log("here",field.val(),data[name],$.inArray(field.val(),data[name]));
					} else {
						field.val(data[name]);
					}
				}
			}
			*/
			
			// new method (use field api)
			var conf = block.conf,script,id,api;
			block.api = {};
			for (i=0;i<conf.fields.length;i++) {
				name = conf.fields[i];
				api = false;
				script = conf.script[name];
				id = "#instance_%0_%1".format(block.id,name);
				if (script) {
					api = eval(script.replace("#[ID]",id));
					api = api.setData ? api : false;
				
				}
				if (!api) {
					api = $(id).peFieldGeneric({api:true});
				}
				block.api[name] = api;
			}
			
			if (data) {
				for (name in data) {
					// this is needed for when a field name is changed but the old one is still present in stored data
					if (block.api[name]) {
						block.api[name].setData(data[name]);
					}
				}
			}
			
		}
		
		function getNextID() {
			return ++blockID;
		}
		
		function getItemField(html,id) {
			return html.replace(/(id|for|name|data-id)="/g,'$1="instance_%0_'.format(id));
		}

		$.extend(this, {
			// public API
			getItemField: getItemField,
			makeSortable: makeSortable,
			populate: populate,
			addBlock: addBlock,
			toggleChooser: toggleChooser,
			getNextID: getNextID,
			fadeIn: fadeIn,
			buttons: function() {
				return buttons.clone();
			},
			target: function () {
				return target;
			},
			destroy: function() {
				target.data("peFieldItems", null);
				target = undefined;
			}
		});
		
		// initialize
		var self = this;
		start();
		
	}
	
	// jQuery plugin implementation
	$.fn.peFieldLayout = function(conf) {
		
		// return existing instance	
		var api = this.data("peFieldLayout");
		
		if (api) { 
			return api; 
		}
		
		conf = $.extend(true, {}, $.pixelentity.peFieldLayout.conf, conf);
		
		// install the plugin for each entry in jQuery object
		this.each(function() {
			var el = $(this);
			api = new PeFieldLayout(el, conf);
			el.data("peFieldLayout", api); 
		});
		
		return conf.api ? api: this;		 
	};
	
	jQuery.fn.peScrollVisible = function(smooth) {
		var cTop = this.offset().top;
		var cHeight = this.outerHeight(true);
		var offset = this.is("li") ? 50 : (this.is("div.buttons") ? 0 : 30);
		cTop += offset;
		var windowTop = $(window).scrollTop();
		var visibleHeight = $(window).height();

		if (cTop < windowTop) {
			if (smooth) {
				$('html,body').animate({'scrollTop': cTop}, 300);
			} else {
				$(window).scrollTop(cTop);
			}
		} else if (cTop + cHeight > windowTop + visibleHeight) {
			if (smooth) {
				$('html,body').animate({'scrollTop': cTop - visibleHeight + cHeight}, 300);
			} else {
				$(window).scrollTop(cTop - visibleHeight + cHeight);
			}
		}
	};
	
}(window.jqpe35 || jQuery));

