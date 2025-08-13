import { useState, useCallback, useEffect } from 'react';

interface SearchHistoryOptions {
  maxHistory?: number;
  storageKey?: string;
  debounceMs?: number;
}

export function useSearchHistory(options: SearchHistoryOptions = {}) {
  const {
    maxHistory = 10,
    storageKey = 'search_history',
    debounceMs = 1000,
  } = options;

  const [searchHistory, setSearchHistory] = useState<string[]>([]);

  // Load search history from localStorage on mount
  useEffect(() => {
    try {
      const stored = localStorage.getItem(storageKey);
      if (stored) {
        const history = JSON.parse(stored);
        if (Array.isArray(history)) {
          setSearchHistory(history.slice(0, maxHistory));
        }
      }
    } catch (error) {
      console.warn('Failed to load search history:', error);
    }
  }, [storageKey, maxHistory]);

  // Save search history to localStorage
  const saveToStorage = useCallback((history: string[]) => {
    try {
      localStorage.setItem(storageKey, JSON.stringify(history));
    } catch (error) {
      console.warn('Failed to save search history:', error);
    }
  }, [storageKey]);

  // Add search to history
  const addToHistory = useCallback((query: string) => {
    if (!query.trim()) return;

    setSearchHistory(prev => {
      // Remove existing entry if it exists
      const filtered = prev.filter(item => item !== query);
      // Add to beginning
      const newHistory = [query, ...filtered].slice(0, maxHistory);
      
      // Save to localStorage
      saveToStorage(newHistory);
      
      return newHistory;
    });
  }, [maxHistory, saveToStorage]);

  // Remove specific search from history
  const removeFromHistory = useCallback((query: string) => {
    setSearchHistory(prev => {
      const newHistory = prev.filter(item => item !== query);
      saveToStorage(newHistory);
      return newHistory;
    });
  }, [saveToStorage]);

  // Clear all search history
  const clearHistory = useCallback(() => {
    setSearchHistory([]);
    try {
      localStorage.removeItem(storageKey);
    } catch (error) {
      console.warn('Failed to clear search history:', error);
    }
  }, [storageKey]);

  // Get search suggestions based on current query
  const getSuggestions = useCallback((query: string, limit: number = 5) => {
    if (!query.trim()) return searchHistory.slice(0, limit);
    
    return searchHistory
      .filter(item => item.toLowerCase().includes(query.toLowerCase()))
      .slice(0, limit);
  }, [searchHistory]);

  return {
    searchHistory,
    addToHistory,
    removeFromHistory,
    clearHistory,
    getSuggestions,
  };
}

// Hook for managing search state with history
export function useSearchWithHistory(
  onSearch: (query: string) => void,
  options: SearchHistoryOptions & { debounceMs?: number } = {}
) {
  const { debounceMs = 500, ...historyOptions } = options;
  const [currentQuery, setCurrentQuery] = useState('');
  const [isSearching, setIsSearching] = useState(false);
  
  const history = useSearchHistory(historyOptions);

  // Debounced search execution
  useEffect(() => {
    if (!currentQuery.trim()) {
      onSearch('');
      setIsSearching(false);
      return;
    }

    setIsSearching(true);
    const timeoutId = setTimeout(() => {
      onSearch(currentQuery);
      history.addToHistory(currentQuery);
      setIsSearching(false);
    }, debounceMs);

    return () => {
      clearTimeout(timeoutId);
    };
  }, [currentQuery, onSearch, history, debounceMs]);

  const updateQuery = useCallback((query: string) => {
    setCurrentQuery(query);
    if (query !== currentQuery) {
      setIsSearching(true);
    }
  }, [currentQuery]);

  const clearSearch = useCallback(() => {
    setCurrentQuery('');
    setIsSearching(false);
  }, []);

  return {
    query: currentQuery,
    isSearching,
    updateQuery,
    clearSearch,
    ...history,
  };
}

// Hook for advanced search with filters
export function useAdvancedSearch(
  onSearch: (query: string, filters: Record<string, any>) => void,
  options: SearchHistoryOptions = {}
) {
  const [query, setQuery] = useState('');
  const [filters, setFilters] = useState<Record<string, any>>({});
  const [isSearching, setIsSearching] = useState(false);
  
  const history = useSearchHistory(options);

  // Parse search query to extract filters
  const parseQuery = useCallback((searchQuery: string) => {
    const extractedFilters: Record<string, any> = {};
    let textQuery = searchQuery;

    // Common filter patterns
    const filterPatterns = [
      { key: 'name', pattern: /name:([^\s]+)/gi },
      { key: 'email', pattern: /email:([^\s]+)/gi },
      { key: 'salary', pattern: /salary:([^\s]+)/gi },
      { key: 'department', pattern: /dept:([^\s]+)/gi },
      { key: 'created', pattern: /created:([^\s]+)/gi },
    ];

    filterPatterns.forEach(({ key, pattern }) => {
      const matches = searchQuery.match(pattern);
      if (matches) {
        matches.forEach(match => {
          const value = match.split(':')[1];
          extractedFilters[key] = value;
          textQuery = textQuery.replace(match, '').trim();
        });
      }
    });

    return { textQuery, filters: extractedFilters };
  }, []);

  // Execute search with debouncing
  useEffect(() => {
    const { textQuery, filters: extractedFilters } = parseQuery(query);
    
    setFilters(extractedFilters);
    setIsSearching(true);

    const timeoutId = setTimeout(() => {
      onSearch(textQuery, extractedFilters);
      if (query.trim()) {
        history.addToHistory(query);
      }
      setIsSearching(false);
    }, 500);

    return () => clearTimeout(timeoutId);
  }, [query, onSearch, parseQuery, history]);

  const updateQuery = useCallback((newQuery: string) => {
    setQuery(newQuery);
  }, []);

  const clearSearch = useCallback(() => {
    setQuery('');
    setFilters({});
    setIsSearching(false);
  }, []);

  const addFilter = useCallback((key: string, value: any) => {
    const filterPrefix = key === 'department' ? 'dept:' : `${key}:`;
    const filterString = `${filterPrefix}${value}`;
    
    setQuery(prev => {
      const existing = prev.includes(filterPrefix);
      if (existing) {
        // Replace existing filter
        return prev.replace(new RegExp(`${filterPrefix}[^\\s]+`, 'g'), filterString);
      } else {
        // Add new filter
        return `${prev} ${filterString}`.trim();
      }
    });
  }, []);

  const removeFilter = useCallback((key: string) => {
    const filterPrefix = key === 'department' ? 'dept:' : `${key}:`;
    setQuery(prev => prev.replace(new RegExp(`${filterPrefix}[^\\s]+`, 'g'), '').trim());
  }, []);

  return {
    query,
    filters,
    isSearching,
    updateQuery,
    clearSearch,
    addFilter,
    removeFilter,
    parseQuery,
    ...history,
  };
}