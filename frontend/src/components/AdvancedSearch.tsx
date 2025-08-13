'use client';

import { useState, useCallback, useRef, useEffect } from 'react';
import Input from './Input';
import Button from './Button';
import { useDebouncedSearch } from '@/hooks/useDebounce';

interface SearchSuggestion {
  type: 'recent' | 'suggestion' | 'filter';
  value: string;
  label: string;
  description?: string;
  count?: number;
}

interface AdvancedSearchProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  suggestions?: SearchSuggestion[];
  onSuggestionSelect?: (suggestion: SearchSuggestion) => void;
  loading?: boolean;
  showSuggestions?: boolean;
  recentSearches?: string[];
  onClearRecent?: () => void;
  searchFilters?: Array<{
    key: string;
    label: string;
    prefix: string;
    examples: string[];
  }>;
}

export default function AdvancedSearch({
  value,
  onChange,
  placeholder = 'Search users...',
  suggestions = [],
  onSuggestionSelect,
  loading = false,
  showSuggestions = true,
  recentSearches = [],
  onClearRecent,
  searchFilters = [],
}: AdvancedSearchProps) {
  const [isFocused, setIsFocused] = useState(false);
  const [showDropdown, setShowDropdown] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Default search filters
  const defaultSearchFilters = [
    {
      key: 'name',
      label: 'Name',
      prefix: 'name:',
      examples: ['name:john', 'name:"John Doe"'],
    },
    {
      key: 'email',
      label: 'Email',
      prefix: 'email:',
      examples: ['email:@company.com', 'email:john@'],
    },
    {
      key: 'salary',
      label: 'Salary',
      prefix: 'salary:',
      examples: ['salary:>50000', 'salary:40000-80000'],
    },
    {
      key: 'department',
      label: 'Department',
      prefix: 'dept:',
      examples: ['dept:engineering', 'dept:sales'],
    },
    {
      key: 'created',
      label: 'Created',
      prefix: 'created:',
      examples: ['created:today', 'created:>2024-01-01'],
    },
  ];

  const allSearchFilters = [...defaultSearchFilters, ...searchFilters];

  // Parse search query to extract filters and text
  const parseSearchQuery = useCallback((query: string) => {
    const filters: Record<string, string> = {};
    let textQuery = query;

    allSearchFilters.forEach(filter => {
      const regex = new RegExp(`${filter.prefix}([^\\s]+)`, 'gi');
      const matches = query.match(regex);
      if (matches) {
        matches.forEach(match => {
          const value = match.replace(filter.prefix, '');
          filters[filter.key] = value;
          textQuery = textQuery.replace(match, '').trim();
        });
      }
    });

    return { filters, textQuery };
  }, [allSearchFilters]);

  // Generate suggestions based on current input
  const generateSuggestions = useCallback((): SearchSuggestion[] => {
    const currentSuggestions: SearchSuggestion[] = [];

    // Add recent searches if no current input
    if (!value.trim() && recentSearches.length > 0) {
      recentSearches.slice(0, 5).forEach(search => {
        currentSuggestions.push({
          type: 'recent',
          value: search,
          label: search,
          description: 'Recent search',
        });
      });
    }

    // Add filter suggestions if typing a filter prefix
    const lastWord = value.split(' ').pop() || '';
    allSearchFilters.forEach(filter => {
      if (filter.prefix.startsWith(lastWord.toLowerCase()) && lastWord.length > 0) {
        filter.examples.forEach(example => {
          currentSuggestions.push({
            type: 'filter',
            value: value.replace(lastWord, example),
            label: example,
            description: `Search by ${filter.label}`,
          });
        });
      }
    });

    // Add provided suggestions
    suggestions.forEach(suggestion => {
      if (suggestion.label.toLowerCase().includes(value.toLowerCase())) {
        currentSuggestions.push(suggestion);
      }
    });

    return currentSuggestions.slice(0, 10); // Limit to 10 suggestions
  }, [value, recentSearches, allSearchFilters, suggestions]);

  const currentSuggestions = generateSuggestions();

  const handleFocus = useCallback(() => {
    setIsFocused(true);
    if (showSuggestions) {
      setShowDropdown(true);
    }
  }, [showSuggestions]);

  const handleBlur = useCallback(() => {
    setIsFocused(false);
    // Delay hiding dropdown to allow for suggestion clicks
    setTimeout(() => setShowDropdown(false), 200);
  }, []);

  const handleSuggestionClick = useCallback((suggestion: SearchSuggestion) => {
    onChange(suggestion.value);
    setShowDropdown(false);
    inputRef.current?.focus();
    onSuggestionSelect?.(suggestion);
  }, [onChange, onSuggestionSelect]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      setShowDropdown(false);
      inputRef.current?.blur();
    }
  }, []);

  const handleClear = useCallback(() => {
    onChange('');
    inputRef.current?.focus();
  }, [onChange]);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target as Node) &&
        !inputRef.current?.contains(event.target as Node)
      ) {
        setShowDropdown(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const { filters: parsedFilters, textQuery } = parseSearchQuery(value);
  const hasFilters = Object.keys(parsedFilters).length > 0;

  return (
    <div className="relative w-full">
      {/* Search Input */}
      <div className="relative">
        <div className="absolute left-3 top-1/2 transform -translate-y-1/2 z-10">
          <svg className="w-5 h-5 text-[#9cabba]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>
        
        <Input
          ref={inputRef}
          variant="search"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          onFocus={handleFocus}
          onBlur={handleBlur}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          isLoading={loading}
          className="pl-10 pr-20"
        />

        {/* Clear button */}
        {value && (
          <button
            onClick={handleClear}
            className="absolute right-12 top-1/2 transform -translate-y-1/2 text-[#9cabba] hover:text-white transition-colors"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        )}

        {/* Help button */}
        <button
          onClick={() => setShowDropdown(!showDropdown)}
          className="absolute right-3 top-1/2 transform -translate-y-1/2 text-[#9cabba] hover:text-white transition-colors"
          title="Search help"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </button>
      </div>

      {/* Active Filters Display */}
      {hasFilters && (
        <div className="mt-2 flex flex-wrap gap-2">
          {Object.entries(parsedFilters).map(([key, value]) => {
            const filter = allSearchFilters.find(f => f.key === key);
            return (
              <span
                key={key}
                className="inline-flex items-center gap-1 px-2 py-1 bg-[#0d80f2]/20 text-[#0d80f2] rounded text-xs"
              >
                {filter?.label || key}: {value}
                <button
                  onClick={() => {
                    const newValue = onChange(
                      value.replace(`${filter?.prefix || key + ':'}${value}`, '').trim()
                    );
                  }}
                  className="hover:text-white"
                >
                  ×
                </button>
              </span>
            );
          })}
        </div>
      )}

      {/* Suggestions Dropdown */}
      {showDropdown && showSuggestions && (
        <div
          ref={dropdownRef}
          className="absolute top-full left-0 right-0 mt-1 bg-[#283039] border border-[#3b4754] rounded-lg shadow-lg z-50 max-h-80 overflow-y-auto"
        >
          {currentSuggestions.length > 0 ? (
            <div className="py-2">
              {currentSuggestions.map((suggestion, index) => (
                <button
                  key={index}
                  onClick={() => handleSuggestionClick(suggestion)}
                  className="w-full px-4 py-2 text-left hover:bg-[#3b4754] transition-colors"
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      {suggestion.type === 'recent' && (
                        <svg className="w-4 h-4 text-[#9cabba]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                      )}
                      {suggestion.type === 'filter' && (
                        <svg className="w-4 h-4 text-[#0d80f2]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                      )}
                      {suggestion.type === 'suggestion' && (
                        <svg className="w-4 h-4 text-[#9cabba]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                      )}
                      <div>
                        <div className="text-white text-sm">{suggestion.label}</div>
                        {suggestion.description && (
                          <div className="text-[#9cabba] text-xs">{suggestion.description}</div>
                        )}
                      </div>
                    </div>
                    {suggestion.count && (
                      <span className="text-[#9cabba] text-xs">{suggestion.count}</span>
                    )}
                  </div>
                </button>
              ))}
            </div>
          ) : (
            <div className="py-8 text-center">
              <div className="text-[#9cabba] text-sm">
                {value ? 'No suggestions found' : 'Start typing to see suggestions'}
              </div>
            </div>
          )}

          {/* Search Help */}
          <div className="border-t border-[#3b4754] p-4">
            <div className="text-white text-sm font-medium mb-2">Search Tips:</div>
            <div className="space-y-1 text-xs text-[#9cabba]">
              <div>• Use quotes for exact phrases: "John Doe"</div>
              <div>• Filter by field: name:john, email:@company.com</div>
              <div>• Salary ranges: salary:50000-80000, salary:{'>'}60000</div>
              <div>• Date filters: created:today, created:{'>'}2024-01-01</div>
            </div>
            {recentSearches.length > 0 && onClearRecent && (
              <Button
                variant="outline"
                size="sm"
                onClick={onClearRecent}
                className="mt-2"
              >
                Clear Recent Searches
              </Button>
            )}
          </div>
        </div>
      )}
    </div>
  );
}