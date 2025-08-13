import { useState, useCallback, useEffect } from 'react';

export interface SearchPreset {
  id: string;
  name: string;
  description?: string;
  search: string;
  filters: Record<string, any>;
  createdAt: string;
  lastUsed?: string;
  useCount: number;
}

interface UseSearchPresetsOptions {
  storageKey?: string;
  maxPresets?: number;
}

export function useSearchPresets(options: UseSearchPresetsOptions = {}) {
  const {
    storageKey = 'search_presets',
    maxPresets = 20,
  } = options;

  const [presets, setPresets] = useState<SearchPreset[]>([]);

  // Load presets from localStorage on mount
  useEffect(() => {
    try {
      const stored = localStorage.getItem(storageKey);
      if (stored) {
        const parsedPresets = JSON.parse(stored);
        if (Array.isArray(parsedPresets)) {
          setPresets(parsedPresets.slice(0, maxPresets));
        }
      }
    } catch (error) {
      console.warn('Failed to load search presets:', error);
    }
  }, [storageKey, maxPresets]);

  // Save presets to localStorage
  const saveToStorage = useCallback((newPresets: SearchPreset[]) => {
    try {
      localStorage.setItem(storageKey, JSON.stringify(newPresets));
    } catch (error) {
      console.warn('Failed to save search presets:', error);
    }
  }, [storageKey]);

  // Create a new preset
  const createPreset = useCallback((
    name: string,
    search: string,
    filters: Record<string, any>,
    description?: string
  ): SearchPreset => {
    const preset: SearchPreset = {
      id: `preset_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
      name,
      description,
      search,
      filters,
      createdAt: new Date().toISOString(),
      useCount: 0,
    };

    setPresets(prev => {
      const newPresets = [preset, ...prev].slice(0, maxPresets);
      saveToStorage(newPresets);
      return newPresets;
    });

    return preset;
  }, [maxPresets, saveToStorage]);

  // Update an existing preset
  const updatePreset = useCallback((
    id: string,
    updates: Partial<Omit<SearchPreset, 'id' | 'createdAt'>>
  ) => {
    setPresets(prev => {
      const newPresets = prev.map(preset =>
        preset.id === id
          ? { ...preset, ...updates }
          : preset
      );
      saveToStorage(newPresets);
      return newPresets;
    });
  }, [saveToStorage]);

  // Delete a preset
  const deletePreset = useCallback((id: string) => {
    setPresets(prev => {
      const newPresets = prev.filter(preset => preset.id !== id);
      saveToStorage(newPresets);
      return newPresets;
    });
  }, [saveToStorage]);

  // Use a preset (increment use count and update last used)
  const usePreset = useCallback((id: string) => {
    const preset = presets.find(p => p.id === id);
    if (!preset) return null;

    updatePreset(id, {
      useCount: preset.useCount + 1,
      lastUsed: new Date().toISOString(),
    });

    return preset;
  }, [presets, updatePreset]);

  // Get popular presets (sorted by use count)
  const getPopularPresets = useCallback((limit: number = 5) => {
    return [...presets]
      .sort((a, b) => b.useCount - a.useCount)
      .slice(0, limit);
  }, [presets]);

  // Get recent presets (sorted by last used or created)
  const getRecentPresets = useCallback((limit: number = 5) => {
    return [...presets]
      .sort((a, b) => {
        const aDate = a.lastUsed || a.createdAt;
        const bDate = b.lastUsed || b.createdAt;
        return new Date(bDate).getTime() - new Date(aDate).getTime();
      })
      .slice(0, limit);
  }, [presets]);

  // Search presets by name or description
  const searchPresets = useCallback((query: string) => {
    if (!query.trim()) return presets;

    const lowerQuery = query.toLowerCase();
    return presets.filter(preset =>
      preset.name.toLowerCase().includes(lowerQuery) ||
      preset.description?.toLowerCase().includes(lowerQuery) ||
      preset.search.toLowerCase().includes(lowerQuery)
    );
  }, [presets]);

  // Clear all presets
  const clearPresets = useCallback(() => {
    setPresets([]);
    try {
      localStorage.removeItem(storageKey);
    } catch (error) {
      console.warn('Failed to clear search presets:', error);
    }
  }, [storageKey]);

  // Export presets as JSON
  const exportPresets = useCallback(() => {
    const dataStr = JSON.stringify(presets, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);
    
    const link = document.createElement('a');
    link.href = url;
    link.download = `search_presets_${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }, [presets]);

  // Import presets from JSON
  const importPresets = useCallback((file: File) => {
    return new Promise<number>((resolve, reject) => {
      const reader = new FileReader();
      
      reader.onload = (e) => {
        try {
          const importedPresets = JSON.parse(e.target?.result as string);
          
          if (!Array.isArray(importedPresets)) {
            reject(new Error('Invalid file format'));
            return;
          }

          // Validate preset structure
          const validPresets = importedPresets.filter(preset =>
            preset.id && preset.name && preset.search !== undefined && preset.filters
          );

          if (validPresets.length === 0) {
            reject(new Error('No valid presets found'));
            return;
          }

          // Merge with existing presets (avoid duplicates by name)
          setPresets(prev => {
            const existingNames = new Set(prev.map(p => p.name));
            const newPresets = validPresets.filter(p => !existingNames.has(p.name));
            const merged = [...prev, ...newPresets].slice(0, maxPresets);
            saveToStorage(merged);
            return merged;
          });

          resolve(validPresets.length);
        } catch (error) {
          reject(new Error('Failed to parse file'));
        }
      };

      reader.onerror = () => reject(new Error('Failed to read file'));
      reader.readAsText(file);
    });
  }, [maxPresets, saveToStorage]);

  return {
    presets,
    createPreset,
    updatePreset,
    deletePreset,
    usePreset,
    getPopularPresets,
    getRecentPresets,
    searchPresets,
    clearPresets,
    exportPresets,
    importPresets,
  };
}

// Hook for managing current search state with presets
export function useSearchWithPresets(
  onSearch: (search: string, filters: Record<string, any>) => void,
  options: UseSearchPresetsOptions = {}
) {
  const [currentSearch, setCurrentSearch] = useState('');
  const [currentFilters, setCurrentFilters] = useState<Record<string, any>>({});
  
  const presets = useSearchPresets(options);

  const applyPreset = useCallback((presetId: string) => {
    const preset = presets.usePreset(presetId);
    if (preset) {
      setCurrentSearch(preset.search);
      setCurrentFilters(preset.filters);
      onSearch(preset.search, preset.filters);
      return preset;
    }
    return null;
  }, [presets, onSearch]);

  const saveCurrentAsPreset = useCallback((name: string, description?: string) => {
    return presets.createPreset(name, currentSearch, currentFilters, description);
  }, [presets, currentSearch, currentFilters]);

  const updateSearch = useCallback((search: string, filters: Record<string, any>) => {
    setCurrentSearch(search);
    setCurrentFilters(filters);
    onSearch(search, filters);
  }, [onSearch]);

  const clearSearch = useCallback(() => {
    setCurrentSearch('');
    setCurrentFilters({});
    onSearch('', {});
  }, [onSearch]);

  // Check if current search matches any preset
  const getCurrentPreset = useCallback(() => {
    return presets.presets.find(preset =>
      preset.search === currentSearch &&
      JSON.stringify(preset.filters) === JSON.stringify(currentFilters)
    );
  }, [presets.presets, currentSearch, currentFilters]);

  return {
    currentSearch,
    currentFilters,
    updateSearch,
    clearSearch,
    applyPreset,
    saveCurrentAsPreset,
    getCurrentPreset,
    ...presets,
  };
}