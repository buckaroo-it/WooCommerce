export const convertUnderScoreToDash = (inputString) => inputString.replace(/_/g, '-');

export const decodeHtmlEntities = (input) => {
  const doc = new DOMParser().parseFromString(input, 'text/html');
  return doc.documentElement.textContent;
};
