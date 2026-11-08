/**
 * Copy a file the person picked into memory, so it can be uploaded.
 *
 * iPhone Safari sends 0 bytes when a photo picked from the library or taken
 * with the camera goes straight into an upload: the picked file is a promise
 * of bytes Safari never keeps. Reading it into memory first gives the upload
 * real bytes to send. Every upload of a picked file should go through this.
 *
 * Rejects when the file can't be read.
 */
export async function copyIntoMemory(file) {
    return new File([await file.arrayBuffer()], file.name, {
        type: file.type,
        lastModified: file.lastModified,
    });
}
