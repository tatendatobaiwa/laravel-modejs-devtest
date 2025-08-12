'use client';

import { useState, useRef, DragEvent } from 'react';
import Button from './Button';

interface FileUploadProps {
  onFileSelect: (file: File) => void;
  acceptedTypes?: string;
  maxSize?: number;
}

export default function FileUpload({ 
  onFileSelect, 
  acceptedTypes = '*/*',
  maxSize = 10 * 1024 * 1024 
}: FileUploadProps) {
  const [isDragOver, setIsDragOver] = useState(false);
  const [error, setError] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleDragOver = (e: DragEvent) => {
    e.preventDefault();
    setIsDragOver(true);
  };

  const handleDragLeave = (e: DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
  };

  const handleDrop = (e: DragEvent) => {
    e.preventDefault();
    setIsDragOver(false);
    
    const files = Array.from(e.dataTransfer.files);
    if (files.length > 0) {
      handleFile(files[0]);
    }
  };

  const handleFileInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files || []);
    if (files.length > 0) {
      handleFile(files[0]);
    }
  };

  const handleFile = (file: File) => {
    setError('');
    
    if (file.size > maxSize) {
      setError(`File size must be less than ${Math.round(maxSize / 1024 / 1024)}MB`);
      return;
    }
    
    onFileSelect(file);
  };

  const handleBrowseClick = () => {
    fileInputRef.current?.click();
  };

  return (
    <div className="flex flex-col p-4">
      <div 
        className={`flex flex-col items-center gap-6 rounded-lg border-2 border-dashed px-6 py-14 transition-colors ${
          isDragOver 
            ? 'border-[#0d80f2] bg-[#0d80f2]/10' 
            : 'border-[#3b4754]'
        }`}
        onDragOver={handleDragOver}
        onDragLeave={handleDragLeave}
        onDrop={handleDrop}
      >
        <div className="flex max-w-[480px] flex-col items-center gap-2">
          <p className="text-white text-lg font-bold leading-tight tracking-[-0.015em] max-w-[480px] text-center">
            Drag and drop your salary documents here
          </p>
          <p className="text-white text-sm font-normal leading-normal max-w-[480px] text-center">
            Or click to browse
          </p>
        </div>
        
        <Button variant="secondary" onClick={handleBrowseClick}>
          Upload
        </Button>
        
        {error && (
          <p className="text-red-500 text-sm text-center">{error}</p>
        )}
      </div>
      
      <input
        ref={fileInputRef}
        type="file"
        accept={acceptedTypes}
        onChange={handleFileInput}
        className="hidden"
      />
    </div>
  );
}
