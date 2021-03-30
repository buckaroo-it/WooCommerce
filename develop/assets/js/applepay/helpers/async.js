const debounce = (func, immediate) => {
  var timeout;
  
	return function() {
		var context = this, args = arguments;
		clearTimeout(timeout);
		if (immediate && !timeout) func.apply(context, args);
		timeout = setTimeout(() => {
			timeout = null;
			if (!immediate) func.apply(context, args);
		}, 250);
	};
}

export { debounce }