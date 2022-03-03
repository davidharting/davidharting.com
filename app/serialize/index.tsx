export const objectToBase64 = (obj: unknown): string => {
  const json = JSON.stringify(obj);
  const base64 = btoa(json);
  const noPadding = base64.replace(/=/g, "").replace(/\+/g, "");
  return noPadding;
};
