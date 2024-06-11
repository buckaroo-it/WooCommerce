const toDecimal = (amount) => Math.round(amount * 100) / 100;

const maxCharacters = (string, amount) => (string.length > amount
  ? `${string.slice(0, amount)}...`
  : string);

export { toDecimal, maxCharacters };
