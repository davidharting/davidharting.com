export const objectToBase64 = (obj: unknown): string => {
  const json = JSON.stringify(obj);
  const base64 = btoa(json);
  const noPadding = base64.replace(/=/g, "").replace(/\+/g, "-");
  return noPadding;
};

export const base64ToObject = (base64: string): unknown => {
  const converted = base64.replace(/-/g, "+");
  const jsonString = atob(converted);
  const object = JSON.parse(jsonString);
  return object;
};
