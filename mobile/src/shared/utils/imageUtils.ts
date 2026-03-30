/**
 * Utility functions for image handling
 */

export interface ImageData {
  uri: string;
  name: string;
  type: string;
}

/**
 * Convert image URI to base64 string using fetch
 */
export const imageToBase64 = async (uri: string): Promise<string | null> => {
  try {
    // Use fetch to read the file
    const response = await fetch(uri);
    const blob = await response.blob();
    
    // Convert blob to base64
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onloadend = () => {
        const result = reader.result as string;
        resolve(result); // Already in data:image/...;base64,... format
      };
      reader.onerror = reject;
      reader.readAsDataURL(blob);
    });
  } catch (error) {
    console.error('Failed to convert image to base64:', error);
    return null;
  }
};

/**
 * Compress image if it's larger than maxSize (in bytes)
 * Returns base64 string or null if compression fails
 */
export const compressImageIfNeeded = async (
  uri: string,
  maxSize: number = 2 * 1024 * 1024 // 2MB default
): Promise<string | null> => {
  try {
    const base64 = await imageToBase64(uri);
    if (!base64) return null;
    
    // Calculate size of base64 string (base64 is ~33% larger than binary)
    // Remove data:image/...;base64, prefix for size calculation
    const base64Data = base64.split(',')[1] || '';
    const base64Size = (base64Data.length * 3) / 4;
    
    // If already under limit, return as is
    if (base64Size <= maxSize) {
      return base64;
    }
    
    // TODO: Implement actual image compression using react-native-image-resizer
    // For now, just return the base64 (backend will handle size validation)
    console.warn('Image size exceeds limit, but compression not implemented yet');
    return base64;
  } catch (error) {
    console.error('Failed to compress image:', error);
    return null;
  }
};
