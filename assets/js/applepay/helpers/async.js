const debounce = (func, immediate) => {
  let timeout;

  return function (...args) {
    const context = this;
    clearTimeout(timeout);
    if (immediate && !timeout) func.apply(context, args);
    timeout = setTimeout(() => {
      timeout = null;
      if (!immediate) func.apply(context, args);
    }, 250);
  };
};

export default debounce;
