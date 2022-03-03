import zod, { z } from "zod";

const createKeyPair = async () => {
  const keyPair = await crypto.subtle.generateKey(
    {
      name: "RSA-OAEP",
      modulusLength: 4096,
      publicExponent: new Uint8Array([1, 0, 1]),
      hash: "SHA-256",
    },
    true,
    ["encrypt", "decrypt"]
  );
  if (!keyPair.privateKey || !keyPair.publicKey) {
    throw new Error("Did not generate keys");
  }
};

const serializeKey = async (key: CryptoKey): Promise<string> => {
  const jsonWebKey: JsonWebKey = await crypto.subtle.exportKey("jwk", key);
  return JSON.stringify(jsonWebKey);
};

const deserializeKey = async (serializedKey: string): Promise<JsonWebKey> => {
  const untypedKey = JSON.parse(serializedKey);
  const result = await zJsonWebKey.safeParseAsync(untypedKey);
  if (result.success) {
    return result.data;
  }
  throw new Error("Unable to parse key");
};

const zJsonWebKey = z.object({
  alg: z.string().optional(),
  crv: z.string().optional(),
  d: z.string().optional(),
  dp: z.string().optional(),
  dq: z.string().optional(),
  e: z.string().optional(),
  ext: z.string().optional(),
  k: z.string().optional(),
  key_ops: z.string().optional(),
  kty: z.string().optional(),
  n: z.string().optional(),
  oth: z.string().optional(),
  p: z.string().optional(),
  q: z.string().optional(),
  qi: z.string().optional(),
  use: z.string().optional(),
  x: z.string().optional(),
  y: z.string().optional(),
});
