jQuery(document).ready(function($) {

	var options = window.dynamicImageOptions;

	function getImageURL(query,src,$container){
		// Optional parameters
		if(!src || !query) return;
		$container = $container || false;
		var pixelRatio = window.devicePixelRatio ? window.devicePixelRatio : 1;

		// For each dimension, multiply by pixel ratio, step to size, and keep below maximums
		// Only setup the dimension if the query needs it

		if(query.match('{w}')){
			width = $container ? $container.width() : 0;
			if(width == 0) return src;
			width = Math.ceil(width * pixelRatio / options.sizeStep) * options.sizeStep;
			width = Math.min(width,options.maxWidth);
			query = query.replace('{w}',width);
		}
		if(query.match('{h}')){
			height = $container ? $container.height() : 0;
			if(height == 0) return src;
			height = Math.ceil(height * pixelRatio / options.sizeStep) * options.sizeStep;
			height = Math.min(height,options.maxWidth);
			query = query.replace('{h}',height);
		}
		if(query.match('{o}')){
			if(!$container) return src;
			
			backgroundPosition = {
				x:$container.css('background-position-x').replace('%',''),
				y:$container.css('background-position-y').replace('%','')
			};

			// Convert pixel values to their percentage equivalent
			if(backgroundPosition.x.match('px')) backgroundPosition.x = backgroundPosition.x.replace('px','') / width  * 100;
			if(backgroundPosition.y.match('px')) backgroundPosition.y = backgroundPosition.y.replace('px','') / height * 100;

			backgroundPosition.x = Math.round(backgroundPosition.x / options.offsetStep) * options.offsetStep;
			backgroundPosition.y = Math.round(backgroundPosition.y / options.offsetStep) * options.offsetStep;
			query = query.replace('{o}',backgroundPosition.x + 'x' + backgroundPosition.y);
		}

		// Pass the extension as a parameter to handle NGINX
		var extension = src.match(/\.([^\.]*)$/)[1];

		return src
			.replace('.'+extension,'')
			.replace(/^((https?:)?\/\/[^\/]*\/|\/)/,
			'$1' + options.baseUrl + '/' + query + '-x' + extension + '/');

	}

	function setupImages(){
		var $items = $('*[data-dynamic-background],*[data-dynamic-image]').not('.setup');

		// Setup containers, set fallbacks
		$items.each(function() {
			var $container = $(this);

			$container.addClass('setup');

			if($container.is('*[data-dynamic-image]')) $container.addClass('dynamic-image');
			if($container.is('*[data-dynamic-background]')) $container.addClass('dynamic-background');

			// You can optionally set a fallback on the container to show a low res placeholder
			if($container.data('dynamic-background-fallback')){
				var background = getImageURL(
					$container.data('dynamic-background-fallback'),
					$container.data('dynamic-background')
					);

				$container.css('background-image','url('+background+')');
			}
		})

		return resizeItems($items);
	}

	function resizeAll()
	{
		return resizeItems($('*[data-dynamic-background],*[data-dynamic-image]'));
	}

	function preloadImage(url,$container){

		// Load into an image first to track loading
		var	deferred = $.Deferred(),
			$preloader = $('<img/>',{
				'class':'preloader'
			});

		// Show background on load
		$preloader.on('load.dynamic-background',function() {
				$preloader.remove();
				deferred.resolve(url);
			})
			.hide()
			.insertAfter($container)
			.attr('src',url);

		return deferred.promise();
	}

	function cancelPreloaders($container)
	{
		$container
			.nextUntil(':not(img.preloader)')
			.off('load.dynamic-background')
			.remove();
	}

	function resizeItems($items){
		return $items.each(function() {

			var $container = $(this),
				current = $container.data('current'),
				type = $container.is('*[data-dynamic-background]') ? 'background' : 'image',
				query = $container.data('dynamic-background-formatting') ||
						(type == 'background' ? 'c{w}x{h}' : 'w{w}'),
				src = (type == 'background'
					? $container.data('dynamic-background')
					: $container.data('dynamic-image'));

			var url = getImageURL(query,src,$container);

			// Couldn't manipulate this src, no width or height
			if(url == src && !$container.is(':visible')) return;

			// Don't load a new image if we already have the correct dimensions queued
			if(current && current == url) return;
			$container.data('current',url);

			// Cancel preloaders
			cancelPreloaders($container);

			if(type == 'background')
				resizeBackground($container,url);
			else
				resizeImage($container,url);

		});
	}

	function resizeBackground($container,url){

		// Remove any pending backgrounds
		$('.preloading',$container).remove();

		// Load in the image
		var $next = $('<div/>',{
			'class':'preloading background',
			'style':'background-image:url('+url+')'
			}).appendTo($container);

		// On image load, remove all other backgrounds
		return preloadImage(url,$container).then(function(){
			$next.removeClass('preloading');

			setTimeout(function() {
				$next.prevAll('.background').remove();
			}, 1000);
		});
	}

	function resizeImage($container,url){
		if(!$container.attr('src')){
			// No source currently, just load it in
			return $container.attr('src',url);
		}else{
			return preloadImage(url,$container).then(function(url){
				return $container.attr('src',url);
			});
		}
	}

	$(window)
		.on('setup.dynamic-image',$.throttle(1000,setupImages))
		.on('resize.dynamic-image',$.throttle(1000,resizeAll));

	insertionQ('*[data-dynamic-background],*[data-dynamic-image]').summary(function(elements){
		setupImages();
		resizeItems($(elements));
	});


	// Give other scripts a chance to size any backgrounds
	setTimeout(function() {
		setupImages();
	}, 0);
});

/*
 * jQuery throttle / debounce - v1.1 - 3/7/2010
 * http://benalman.com/projects/jquery-throttle-debounce-plugin/
 * 
 * Copyright (c) 2010 "Cowboy" Ben Alman
 * Dual licensed under the MIT and GPL licenses.
 * http://benalman.com/about/license/
 */
(function(b,c){var $=b.jQuery||b.Cowboy||(b.Cowboy={}),a;$.throttle=a=function(e,f,j,i){var h,d=0;if(typeof f!=="boolean"){i=j;j=f;f=c}function g(){var o=this,m=+new Date()-d,n=arguments;function l(){d=+new Date();j.apply(o,n)}function k(){h=c}if(i&&!h){l()}h&&clearTimeout(h);if(i===c&&m>e){l()}else{if(f!==true){h=setTimeout(i?k:l,i===c?e-m:e)}}}if($.guid){g.guid=j.guid=j.guid||$.guid++}return g};$.debounce=function(d,e,f){return f===c?a(d,e,false):a(d,f,e!==false)}})(this);


// https://github.com/naugtur/insertionQuery

var insertionQ = (function () {
    "use strict";

    var sequence = 100,
        isAnimationSupported = false,
        animationstring = 'animationName',
        keyframeprefix = '',
        domPrefixes = 'Webkit Moz O ms Khtml'.split(' '),
        pfx = '',
        elm = document.createElement('div'),
        options = {
            strictlyNew: true,
            timeout: 20
        };

    if (elm.style.animationName) {
        isAnimationSupported = true;
    }

    if (isAnimationSupported === false) {
        for (var i = 0; i < domPrefixes.length; i++) {
            if (elm.style[domPrefixes[i] + 'AnimationName'] !== undefined) {
                pfx = domPrefixes[i];
                animationstring = pfx + 'AnimationName';
                keyframeprefix = '-' + pfx.toLowerCase() + '-';
                isAnimationSupported = true;
                break;
            }
        }
    }


    function listen(selector, callback) {
        var styleAnimation, animationName = 'insQ_' + (sequence++);

        var eventHandler = function (event) {
            if (event.animationName === animationName || event[animationstring] === animationName) {
                if (!isTagged(event.target)) {
                    callback(event.target);
                }
            }
        };

        styleAnimation = document.createElement('style');
        styleAnimation.innerHTML = '@' + keyframeprefix + 'keyframes ' + animationName + ' {  from {  outline: 1px solid transparent  } to {  outline: 0px solid transparent }  }' +
            "\n" + selector + ' { animation-duration: 0.001s; animation-name: ' + animationName + '; ' +
            keyframeprefix + 'animation-duration: 0.001s; ' + keyframeprefix + 'animation-name: ' + animationName + '; ' +
            ' } ';

        document.head.appendChild(styleAnimation);

        var bindAnimationLater = setTimeout(function () {
            document.addEventListener('animationstart', eventHandler, false);
            document.addEventListener('MSAnimationStart', eventHandler, false);
            document.addEventListener('webkitAnimationStart', eventHandler, false);
            //event support is not consistent with DOM prefixes
        }, options.timeout); //starts listening later to skip elements found on startup. this might need tweaking

        return {
            destroy: function () {
                clearTimeout(bindAnimationLater);
                if (styleAnimation) {
                    document.head.removeChild(styleAnimation);
                    styleAnimation = null;
                }
                document.removeEventListener('animationstart', eventHandler);
                document.removeEventListener('MSAnimationStart', eventHandler);
                document.removeEventListener('webkitAnimationStart', eventHandler);
            }
        };
    }


    function tag(el) {
        el.QinsQ = true; //bug in V8 causes memory leaks when weird characters are used as field names. I don't want to risk leaking DOM trees so the key is not '-+-' anymore
    }

    function isTagged(el) {
        return (options.strictlyNew && (el.QinsQ === true));
    }

    function topmostUntaggedParent(el) {
        if (isTagged(el.parentNode)) {
            return el;
        } else {
            return topmostUntaggedParent(el.parentNode);
        }
    }

    function tagAll(e) {
        tag(e);
        e = e.firstChild;
        for (; e; e = e.nextSibling) {
            if (e !== undefined && e.nodeType === 1) {
                tagAll(e);
            }
        }
    }

    //aggregates multiple insertion events into a common parent
    function catchInsertions(selector, callback) {
        var insertions = [];
        //throttle summary
        var sumUp = (function () {
            var to;
            return function () {
                clearTimeout(to);
                to = setTimeout(function () {
                    insertions.forEach(tagAll);
                    callback(insertions);
                    insertions = [];
                }, 10);
            };
        })();

        return listen(selector, function (el) {
            if (isTagged(el)) {
                return;
            }
            tag(el);
            var myparent = topmostUntaggedParent(el);
            if (insertions.indexOf(myparent) < 0) {
                insertions.push(myparent);
            }
            sumUp();
        });
    }

    //insQ function
    var exports = function (selector) {
        if (isAnimationSupported && selector.match(/[^{}]/)) {

            if (options.strictlyNew) {
                tagAll(document.body); //prevents from catching things on show
            }
            return {
                every: function (callback) {
                    return listen(selector, callback);
                },
                summary: function (callback) {
                    return catchInsertions(selector, callback);
                }
            };
        } else {
            return false;
        }
    };

    //allows overriding defaults
    exports.config = function (opt) {
        for (var o in opt) {
            if (opt.hasOwnProperty(o)) {
                options[o] = opt[o];
            }
        }
    };

    return exports;
})();

if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
    module.exports = insertionQ;
}