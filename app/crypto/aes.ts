const decoder = new TextDecoder();
const encoder = new TextEncoder();

const ALGORITHM = "AES-GCM";
const KEY_OPTIONS: [AesKeyGenParams, boolean, ["encrypt", "decrypt"]] = [
  { name: ALGORITHM, length: 256 },
  true,
  ["encrypt", "decrypt"],
];

interface CreateKey {
  (): Promise<{ key: CryptoKey; exported: string }>;
}

export const createKey: CreateKey = async () => {
  const key = await crypto.subtle.generateKey(...KEY_OPTIONS);
  const exportedKeyBytes = await crypto.subtle.exportKey("raw", key);
  console.log({ exportedKeyBytes });
  const exportedKey = decoder.decode(exportedKeyBytes);
  console.log({ exportedKey });
  return {
    key,
    exported: exportedKey,
  };
};

interface Encrypt {
  (key: CryptoKey, plaintext: string): Promise<{
    ciphertext: string;
    iv: string;
  }>;
}

export const encrypt: Encrypt = async (key, plaintext) => {
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const encryptedBytes: ArrayBuffer = await crypto.subtle.encrypt(
    { name: ALGORITHM, iv },
    key,
    encoder.encode(plaintext)
  );
  return { ciphertext: decoder.decode(encryptedBytes), iv: decoder.decode(iv) };
};

interface Decrypt {
  (serializedKey: string, iv: string, ciphertext: string): Promise<string>;
}

export const decrypt: Decrypt = async (serializedKey, iv, ciphertext) => {
  console.log("Decrypt called", { serializedKey, iv, ciphertext });
  const encodedKey = encoder.encode(serializedKey);
  console.log({ encodedKey });
  const encodedIv = encoder.encode(iv);
  console.log({ encodedIv });
  const key = await crypto.subtle.importKey("raw", encodedKey, ...KEY_OPTIONS);
  console.log("Imported key", { key });
  const encodedCiphertext = encoder.encode(ciphertext);
  console.log({ encodedCiphertext });
  const plaintextBytes: ArrayBuffer = await crypto.subtle.decrypt(
    { name: "AES-GCM", iv: encodedIv },
    key,
    encodedCiphertext
  );
  console.log({ plaintextBytes });
  return decoder.decode(plaintextBytes);
};
