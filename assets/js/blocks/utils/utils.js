export const convertUnderScoreToDash = inputString => inputString.replace(/_/g, '-');

export const decodeHtmlEntities = input => {
    const doc = new DOMParser().parseFromString(input, 'text/html');
    return doc.documentElement.textContent;
};

// True when `date` is the day-first reading (d-M-yyyy, with -, / or . as separator)
// of the text typed into a date field. Matches the dateFormat list of the birthdate field.
export const matchesDayFirstDate = (text, date) => {
    const match = /^(\d{1,2})([-/.])(\d{1,2})\2(\d{4})$/.exec(String(text).trim());
    if (!match || !(date instanceof Date) || Number.isNaN(date.getTime())) {
        return false;
    }

    return (
        date.getDate() === Number(match[1]) &&
        date.getMonth() + 1 === Number(match[3]) &&
        date.getFullYear() === Number(match[4])
    );
};
