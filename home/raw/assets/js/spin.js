(function(){"use strict";var a,b;a=jQuery,b=function(){function b(b,c){this.$element=b,this.options=a.extend({},this.defaults,c),this.configure()}return b.prototype.defaults={petals:9},b.prototype.show=function(){return this.$element.animate({opacity:1})},b.prototype.hide=function(){return this.$element.animate({opacity:0})},b.prototype.destroy=function(){return this.$element.empty(),this.$element.data("spin",void 0)},b.prototype.configure=function(){var b,c,d,e,f;for(this.$element.empty(),f=[],c=d=0,e=this.options.petals;e>=0?e>d:d>e;c=e>=0?++d:--d)b=a("<div />"),f.push(this.$element.append(b));return f},b}(),a.fn.spin=function(c){return a(this).each(function(){var d,e;return d=a(this),e=d.data("spinner"),null==e&&d.data("spinner",e=new b(d,c)),"string"==typeof c?e[c]():void 0})},a(function(){return a("[data-spin]").spin()})}).call(this);