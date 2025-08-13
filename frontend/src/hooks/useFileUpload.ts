import { useState, useCallback } from 'react';

export interface FileUploadState {
  file: File | null;
  progress: number;
  isUploading: boolean;
  error: string | null;
  uploadComplete: boolean;
}

interface UseFileUploadOptions {
  maxSize?: number;
  acceptedTypes?: string[];
  onProgress?: (progress: number) => void;
  onComplete?: (file: File) => void;
  onError?: (error: string) => void;
}

export function useFileUpload({
  maxSize = 5 * 1024 * 1024, // 5MB default
  acceptedTypes = ['.pdf', '.doc', '.docx', '.xls', '.xlsx'],
  onProgress,
  onComplete,
  onError,
}: UseFileUploadOptions = {}) {
  const [state, setState] = useState<FileUploadState>({
    file: null,
    progress: 0,
    isUploading: false,
    error: null,
    uploadComplete: false,
  });

  const validateFile = useCallback((file: File): string | null => {
    // Check file size
    if (file.size > maxSize) {
      return `File size must be less than ${Math.round(maxSize / 1024 / 1024)}MB`;
    }

    // Check file type
    const fileExtension = '.' + file.name.split('.').pop()?.toLowerCase();
    if (acceptedTypes.length > 0 && !acceptedTypes.includes(fileExtension)) {
      return `File type not supported. Accepted types: ${acceptedTypes.join(', ')}`;
    }

    return null;
  }, [maxSize, acceptedTypes]);

  const selectFile = useCallback((file: File) => {
    const validationError = validateFile(file);
    
    if (validationError) {
      setState(prev => ({
        ...prev,
        error: validationError,
        file: null,
      }));
      onError?.(validationError);
      return false;
    }

    setState(prev => ({
      ...prev,
      file,
      error: null,
      progress: 0,
      uploadComplete: false,
    }));

    return true;
  }, [validateFile, onError]);

  const updateProgress = useCallback((progress: number) => {
    setState(prev => ({
      ...prev,
      progress: Math.min(100, Math.max(0, progress)),
      isUploading: progress < 100,
      uploadComplete: progress >= 100,
    }));
    
    onProgress?.(progress);
    
    if (progress >= 100 && state.file) {
      onComplete?.(state.file);
    }
  }, [onProgress, onComplete, state.file]);

  const startUpload = useCallback(() => {
    setState(prev => ({
      ...prev,
      isUploading: true,
      progress: 0,
      error: null,
      uploadComplete: false,
    }));
  }, []);

  const setError = useCallback((error: string) => {
    setState(prev => ({
      ...prev,
      error,
      isUploading: false,
      progress: 0,
      uploadComplete: false,
    }));
    onError?.(error);
  }, [onError]);

  const reset = useCallback(() => {
    setState({
      file: null,
      progress: 0,
      isUploading: false,
      error: null,
      uploadComplete: false,
    });
  }, []);

  const removeFile = useCallback(() => {
    setState(prev => ({
      ...prev,
      file: null,
      progress: 0,
      uploadComplete: false,
      error: null,
    }));
  }, []);

  return {
    ...state,
    selectFile,
    updateProgress,
    startUpload,
    setError,
    reset,
    removeFile,
    validateFile,
  };
}