'use client';

import { useState, useCallback, useMemo, useEffect } from 'react';
import Button from './Button';
import Input from './Input';
import Modal from './Modal';

interface FilterOption {
  key: string;
  label: string;
  type: 'text' | 'number' | 'select' | 'date' | 'range' | 'multiselect';
  options?: Array<{ value: string; label: string }>;
  placeholder?: string;
  min?: number;
  max?: number;
  step?: number;
  validation?: (value: any) => string | null;
  dependsOn?: string; // Filter depends on another filter
  conditional?: (filters: Record<string, any>) => boolean; // Show filter conditionally
}

interface AdvancedFiltersProps {
  filters: Record<string, any>;
  onFiltersChange: (filters: Record<string, any>) => void;
  filterOptions: FilterOption[];
  onReset: () => void;
  loading?: boolean;
  showActiveCount?: boolean;
}

export default function AdvancedFilters({
  filters,
  onFiltersChange,
  filterOptions,
  onReset,
  loading = false,
  showActiveCount = true,
}: AdvancedFiltersProps) {
  const [showModal, setShowModal] = useState(false);
  const [tempFilters, setTempFilters] = useState(filters);
  const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});

  // Sync temp filters when external filters change
  useEffect(() => {
    setTempFilters(filters);
  }, [filters]);

  // Get visible filter options based on conditions
  const visibleFilterOptions = useMemo(() => {
    return filterOptions.filter(option => {
      if (option.conditional) {
        return option.conditional(tempFilters);
      }
      return true;
    });
  }, [filterOptions, tempFilters]);

  // Validate a single filter value
  const validateFilter = useCallback((option: FilterOption, value: any): string | null => {
    if (option.validation) {
      return option.validation(value);
    }

    // Built-in validations
    if (option.type === 'number' && value !== '' && value !== null && value !== undefined) {
      const num = Number(value);
      if (isNaN(num)) return 'Must be a valid number';
      if (option.min !== undefined && num < option.min) return `Must be at least ${option.min}`;
      if (option.max !== undefined && num > option.max) return `Must be at most ${option.max}`;
    }

    if (option.type === 'range') {
      const minKey = `${option.key}_min`;
      const maxKey = `${option.key}_max`;
      const minValue = tempFilters[minKey];
      const maxValue = tempFilters[maxKey];
      
      if (minValue && maxValue && Number(minValue) > Number(maxValue)) {
        return 'Minimum value cannot be greater than maximum value';
      }
    }

    return null;
  }, [tempFilters]);

  const handleTempFilterChange = useCallback((key: string, value: any) => {
    setTempFilters(prev => {
      const newFilters = { ...prev, [key]: value };
      
      // Clear dependent filters when parent changes
      const dependentOptions = filterOptions.filter(opt => opt.dependsOn === key);
      dependentOptions.forEach(opt => {
        if (opt.key in newFilters) {
          delete newFilters[opt.key];
        }
      });
      
      return newFilters;
    });

    // Clear validation error for this field
    setValidationErrors(prev => {
      const newErrors = { ...prev };
      delete newErrors[key];
      return newErrors;
    });
  }, [filterOptions]);

  const handleApplyFilters = useCallback(() => {
    // Validate all filters before applying
    const errors: Record<string, string> = {};
    
    visibleFilterOptions.forEach(option => {
      if (option.type === 'range') {
        const minKey = `${option.key}_min`;
        const maxKey = `${option.key}_max`;
        const error = validateFilter(option, null);
        if (error) {
          errors[option.key] = error;
        }
      } else {
        const value = tempFilters[option.key];
        const error = validateFilter(option, value);
        if (error) {
          errors[option.key] = error;
        }
      }
    });

    if (Object.keys(errors).length > 0) {
      setValidationErrors(errors);
      return;
    }

    // Remove empty filters and apply
    const cleanedFilters = Object.entries(tempFilters).reduce((acc, [key, value]) => {
      if (value !== '' && value !== null && value !== undefined) {
        // Handle array values for multiselect
        if (Array.isArray(value) && value.length > 0) {
          acc[key] = value;
        } else if (!Array.isArray(value)) {
          acc[key] = value;
        }
      }
      return acc;
    }, {} as Record<string, any>);

    onFiltersChange(cleanedFilters);
    setShowModal(false);
    setValidationErrors({});
  }, [tempFilters, onFiltersChange, visibleFilterOptions, validateFilter]);

  const handleResetFilters = useCallback(() => {
    setTempFilters({});
    setValidationErrors({});
    onReset();
    setShowModal(false);
  }, [onReset]);

  const activeFilterCount = Object.keys(filters).length;

  // Quick filter presets
  const quickFilters = useMemo(() => [
    {
      key: 'salary_range_high',
      label: 'High Salary (>€80k)',
      filters: { salary_min: 80000 },
      active: filters.salary_min >= 80000,
    },
    {
      key: 'salary_range_medium',
      label: 'Medium Salary (€40k-€80k)',
      filters: { salary_min: 40000, salary_max: 80000 },
      active: filters.salary_min >= 40000 && filters.salary_max <= 80000,
    },
    {
      key: 'recent_7days',
      label: 'Last 7 Days',
      filters: { created_from: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0] },
      active: filters.created_from === new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    },
    {
      key: 'recent_30days',
      label: 'Last 30 Days',
      filters: { created_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0] },
      active: filters.created_from === new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    },
    {
      key: 'high_commission',
      label: 'High Commission (>€1000)',
      filters: { commission_min: 1000 },
      active: filters.commission_min >= 1000,
    },
  ], [filters]);

  const handleQuickFilter = useCallback((quickFilter: typeof quickFilters[0]) => {
    if (quickFilter.active) {
      // Remove the quick filter
      const newFilters = { ...filters };
      Object.keys(quickFilter.filters).forEach(key => {
        delete newFilters[key];
      });
      onFiltersChange(newFilters);
    } else {
      // Apply the quick filter
      onFiltersChange({ ...filters, ...quickFilter.filters });
    }
  }, [filters, onFiltersChange]);

  const renderFilterInput = useCallback((option: FilterOption) => {
    const value = tempFilters[option.key] || '';
    const error = validationErrors[option.key];

    switch (option.type) {
      case 'select':
        return (
          <div className="space-y-1">
            <select
              value={value}
              onChange={(e) => handleTempFilterChange(option.key, e.target.value)}
              className={`w-full bg-[#283039] text-white rounded-lg px-3 py-2 border-none focus:outline-none focus:ring-2 ${
                error ? 'focus:ring-red-500 ring-1 ring-red-500' : 'focus:ring-[#0d80f2]'
              }`}
            >
              <option value="">All {option.label}</option>
              {option.options?.map(opt => (
                <option key={opt.value} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
            {error && <p className="text-red-400 text-xs">{error}</p>}
          </div>
        );

      case 'multiselect':
        const selectedValues = Array.isArray(value) ? value : [];
        return (
          <div className="space-y-2">
            <div className="max-h-32 overflow-y-auto space-y-1">
              {option.options?.map(opt => (
                <label key={opt.value} className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={selectedValues.includes(opt.value)}
                    onChange={(e) => {
                      const newValues = e.target.checked
                        ? [...selectedValues, opt.value]
                        : selectedValues.filter(v => v !== opt.value);
                      handleTempFilterChange(option.key, newValues);
                    }}
                    className="h-4 w-4 rounded border-[#3b4754] border-2 bg-transparent text-[#0d80f2] checked:bg-[#0d80f2] checked:border-[#0d80f2]"
                  />
                  <span className="text-white">{opt.label}</span>
                </label>
              ))}
            </div>
            {selectedValues.length > 0 && (
              <div className="flex flex-wrap gap-1">
                {selectedValues.map(val => {
                  const opt = option.options?.find(o => o.value === val);
                  return (
                    <span
                      key={val}
                      className="inline-flex items-center gap-1 px-2 py-1 bg-[#0d80f2]/20 text-[#0d80f2] rounded text-xs"
                    >
                      {opt?.label || val}
                      <button
                        onClick={() => {
                          const newValues = selectedValues.filter(v => v !== val);
                          handleTempFilterChange(option.key, newValues);
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
            {error && <p className="text-red-400 text-xs">{error}</p>}
          </div>
        );

      case 'range':
        const minKey = `${option.key}_min`;
        const maxKey = `${option.key}_max`;
        const rangeError = validationErrors[option.key];
        return (
          <div className="space-y-1">
            <div className="grid grid-cols-2 gap-2">
              <Input
                type="number"
                placeholder={`Min ${option.label}`}
                value={tempFilters[minKey] || ''}
                onChange={(e) => handleTempFilterChange(minKey, e.target.value)}
                min={option.min}
                max={option.max}
                step={option.step}
                error={!!rangeError}
              />
              <Input
                type="number"
                placeholder={`Max ${option.label}`}
                value={tempFilters[maxKey] || ''}
                onChange={(e) => handleTempFilterChange(maxKey, e.target.value)}
                min={option.min}
                max={option.max}
                step={option.step}
                error={!!rangeError}
              />
            </div>
            {rangeError && <p className="text-red-400 text-xs">{rangeError}</p>}
          </div>
        );

      case 'date':
        const fromKey = `${option.key}_from`;
        const toKey = `${option.key}_to`;
        return (
          <div className="space-y-1">
            <div className="grid grid-cols-2 gap-2">
              <Input
                type="date"
                placeholder={`From ${option.label}`}
                value={tempFilters[fromKey] || ''}
                onChange={(e) => handleTempFilterChange(fromKey, e.target.value)}
                error={!!error}
              />
              <Input
                type="date"
                placeholder={`To ${option.label}`}
                value={tempFilters[toKey] || ''}
                onChange={(e) => handleTempFilterChange(toKey, e.target.value)}
                error={!!error}
              />
            </div>
            {error && <p className="text-red-400 text-xs">{error}</p>}
          </div>
        );

      default:
        return (
          <div className="space-y-1">
            <Input
              type={option.type}
              placeholder={option.placeholder || `Filter by ${option.label}`}
              value={value}
              onChange={(e) => handleTempFilterChange(option.key, e.target.value)}
              min={option.min}
              max={option.max}
              step={option.step}
              error={!!error}
            />
            {error && <p className="text-red-400 text-xs">{error}</p>}
          </div>
        );
    }
  }, [tempFilters, handleTempFilterChange, validationErrors]);

  return (
    <>
      {/* Quick Filters */}
      <div className="flex gap-3 p-3 flex-wrap pr-4">
        {quickFilters.map(quickFilter => (
          <Button
            key={quickFilter.key}
            variant={quickFilter.active ? 'primary' : 'outline'}
            size="sm"
            onClick={() => handleQuickFilter(quickFilter)}
            disabled={loading}
          >
            {quickFilter.label}
          </Button>
        ))}

        <Button
          variant="outline"
          size="sm"
          onClick={() => {
            setTempFilters(filters);
            setValidationErrors({});
            setShowModal(true);
          }}
          disabled={loading}
        >
          Advanced Filters
          {showActiveCount && activeFilterCount > 0 && (
            <span className="ml-2 bg-[#0d80f2] text-white rounded-full px-2 py-1 text-xs">
              {activeFilterCount}
            </span>
          )}
        </Button>

        {activeFilterCount > 0 && (
          <Button 
            variant="outline" 
            size="sm" 
            onClick={onReset}
            disabled={loading}
          >
            Clear All ({activeFilterCount})
          </Button>
        )}

        {loading && (
          <div className="flex items-center gap-2 text-[#9cabba] text-sm">
            <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-[#0d80f2]"></div>
            <span>Filtering...</span>
          </div>
        )}
      </div>

      {/* Advanced Filters Modal */}
      <Modal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        title="Advanced Filters"
        size="lg"
        actions={
          <>
            <Button variant="outline" onClick={handleResetFilters}>
              Reset All
            </Button>
            <Button variant="outline" onClick={() => setShowModal(false)}>
              Cancel
            </Button>
            <Button onClick={handleApplyFilters}>
              Apply Filters
            </Button>
          </>
        }
      >
        <div className="space-y-6">
          <p className="text-[#9cabba] text-sm">
            Use advanced filters to narrow down your search results. All filters are combined with AND logic.
          </p>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            {visibleFilterOptions.map((option) => (
              <div key={option.key} className="space-y-2">
                <label className="text-white text-sm font-medium">
                  {option.label}
                  {option.dependsOn && (
                    <span className="text-[#9cabba] text-xs ml-2">
                      (depends on {filterOptions.find(f => f.key === option.dependsOn)?.label})
                    </span>
                  )}
                </label>
                {renderFilterInput(option)}
              </div>
            ))}
          </div>

          {visibleFilterOptions.length === 0 && (
            <div className="text-center py-8">
              <p className="text-[#9cabba] text-sm">
                No filters available. Please adjust your current selections.
              </p>
            </div>
          )}

          {/* Active Filters Preview */}
          {Object.keys(tempFilters).length > 0 && (
            <div className="border-t border-[#3b4754] pt-4">
              <div className="flex items-center justify-between mb-2">
                <h4 className="text-white text-sm font-medium">Active Filters:</h4>
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setTempFilters({});
                    setValidationErrors({});
                  }}
                >
                  Clear All
                </Button>
              </div>
              <div className="flex flex-wrap gap-2">
                {Object.entries(tempFilters).map(([key, value]) => {
                  if (!value || (Array.isArray(value) && value.length === 0)) return null;
                  
                  const option = filterOptions.find(opt => 
                    opt.key === key || 
                    key.startsWith(opt.key + '_')
                  );
                  
                  let displayValue: string;
                  
                  if (Array.isArray(value)) {
                    displayValue = value.map(v => 
                      option?.options?.find(opt => opt.value === v)?.label || v
                    ).join(', ');
                  } else if (option?.type === 'select') {
                    displayValue = option.options?.find(opt => opt.value === value)?.label || value;
                  } else if (option?.type === 'date') {
                    displayValue = new Date(value).toLocaleDateString();
                  } else if (option?.type === 'number' && option.key.includes('salary')) {
                    displayValue = new Intl.NumberFormat('en-US', {
                      style: 'currency',
                      currency: 'EUR',
                      minimumFractionDigits: 0,
                    }).format(Number(value));
                  } else {
                    displayValue = String(value);
                  }

                  const displayKey = key
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, l => l.toUpperCase())
                    .replace(' Min', ' ≥')
                    .replace(' Max', ' ≤')
                    .replace(' From', ' ≥')
                    .replace(' To', ' ≤');

                  return (
                    <span
                      key={key}
                      className="inline-flex items-center gap-1 px-2 py-1 bg-[#0d80f2]/20 text-[#0d80f2] rounded text-xs"
                    >
                      {displayKey}: {displayValue}
                      <button
                        onClick={() => handleTempFilterChange(key, Array.isArray(value) ? [] : '')}
                        className="hover:text-white"
                      >
                        ×
                      </button>
                    </span>
                  );
                })}
              </div>
            </div>
          )}

          {/* Filter Summary */}
          {Object.keys(tempFilters).length > 0 && (
            <div className="border-t border-[#3b4754] pt-4">
              <div className="flex items-center gap-2 text-sm text-[#9cabba]">
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>
                  {Object.keys(tempFilters).length} filter{Object.keys(tempFilters).length !== 1 ? 's' : ''} will be applied.
                  All filters are combined with AND logic.
                </span>
              </div>
            </div>
          )}
        </div>
      </Modal>
    </>
  );
}