'use client';

import { useState, useRef, DragEvent } from 'react';
import Button from './Button';

interface FileUploadProps {
  onFileSelect: (file: File) => void;
  acceptedTypes?: string;
  maxSize?: number;
  progress?: number;
  isUploading?: boolean;
  error?: string;
  selectedFile?: File | null;
  onRemoveFile?: () => void;
  onRetry?: () => void;
}

export default function FileUpload({ 
  onFileSelect, 
  acceptedTypes = '*/*',
  maxSize = 10 * 1024 * 1024,
  progress = 0,
  isUploading = false,
  error,
  selectedFile,
  onRemoveFile,
  onRetry
}: FileUploadProps) {
  const [isDragOver, setIsDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    if (!isUploading) {
      setIsDragOver(true);
    }
  };

  const handleDragLeave = (e: DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
  };

  const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    
    if (isUploading) return;
    
    const files = Array.from(e.dataTransfer.files);
    if (files.length > 0) {
      handleFile(files[0]);
    }
  };

  const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (isUploading) return;
    
    const files = Array.from(e.target.files || []);
    if (files.length > 0) {
      handleFile(files[0]);
    }
    
    // Reset input value to allow selecting the same file again
    e.target.value = '';
  };

  const handleFile = (file: File) => {
    if (file.size > maxSize) {
      return; // Let parent handle validation
    }
    
    onFileSelect(file);
  };

  const handleBrowseClick = () => {
    if (!isUploading) {
      fileInputRef.current?.click();
    }
  };

  const formatFileSize = (bytes: number): string => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  return (
    <div className="flex flex-col p-4">
      {selectedFile ? (
        <div className="flex flex-col gap-4">
          {/* Selected File Display */}
          <div className="flex items-center justify-between p-4 bg-[#283039] rounded-lg">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 bg-[#0d80f2] rounded-lg flex items-center justify-center">
                <svg className="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              </div>
              <div>
                <p className="text-white text-sm font-medium">{selectedFile.name}</p>
                <p className="text-[#9cabba] text-xs">{formatFileSize(selectedFile.size)}</p>
              </div>
            </div>
            
            {!isUploading && (
              <Button 
                variant="secondary" 
                onClick={onRemoveFile}
                className="text-xs px-3 py-1"
              >
                Remove
              </Button>
            )}
          </div>

          {/* Progress Bar */}
          {isUploading && (
            <div className="flex flex-col gap-2">
              <div className="flex justify-between text-sm">
                <span className="text-white">Uploading...</span>
                <span className="text-[#9cabba]">{Math.round(progress)}%</span>
              </div>
              <div className="w-full bg-[#3b4754] rounded-full h-2">
                <div 
                  className="bg-[#0d80f2] h-2 rounded-full transition-all duration-300 ease-out"
                  style={{ width: `${progress}%` }}
                />
              </div>
            </div>
          )}

          {/* Error Display with Retry */}
          {error && !isUploading && (
            <div className="flex items-center justify-between p-3 bg-red-500/10 border border-red-500/20 rounded-lg">
              <div className="flex items-center gap-2">
                <svg className="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p className="text-red-400 text-sm">{error}</p>
              </div>
              {onRetry && (
                <Button 
                  variant="secondary" 
                  onClick={onRetry}
                  className="text-xs px-3 py-1"
                >
                  Retry
                </Button>
              )}
            </div>
          )}
        </div>
      ) : (
        /* Upload Area */
        <div 
          className={`flex flex-col items-center gap-6 rounded-lg border-2 border-dashed px-6 py-14 transition-colors ${
            isDragOver 
              ? 'border-[#0d80f2] bg-[#0d80f2]/10' 
              : 'border-[#3b4754]'
          } ${isUploading ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          onClick={handleBrowseClick}
        >
          <div className="flex max-w-[480px] flex-col items-center gap-2">
            <svg className="w-12 h-12 text-[#9cabba] mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <p className="text-white text-lg font-bold leading-tight tracking-[-0.015em] max-w-[480px] text-center">
              Drag and drop your salary documents here
            </p>
            <p className="text-[#9cabba] text-sm font-normal leading-normal max-w-[480px] text-center">
              Or click to browse • Max {Math.round(maxSize / 1024 / 1024)}MB
            </p>
            <p className="text-[#9cabba] text-xs font-normal leading-normal max-w-[480px] text-center">
              Supported formats: PDF, DOC, DOCX, XLS, XLSX
            </p>
          </div>
          
          <Button 
            variant="secondary" 
            disabled={isUploading}
            className={isUploading ? 'opacity-50 cursor-not-allowed' : ''}
          >
            {isUploading ? 'Uploading...' : 'Choose File'}
          </Button>
        </div>
      )}
      
      <input
        ref={fileInputRef}
        type="file"
        accept={acceptedTypes}
        onChange={handleFileInput}
        disabled={isUploading}
        className="hidden"
      />
    </div>
  );
}
