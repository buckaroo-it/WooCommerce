export const convertUnderScoreToDash = (inputString) => {
    return inputString.replace(/_/g, '-');
};

export const decodeHtmlEntities = (input) => {
    var doc = new DOMParser().parseFromString(input, "text/html");
    return doc.documentElement.textContent;
};