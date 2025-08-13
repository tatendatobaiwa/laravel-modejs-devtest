'use client';

import { useState, useCallback } from 'react';
import Button from './Button';
import Input from './Input';

interface Column {
  key: string;
  label: string;
  sortable?: boolean;
  editable?: boolean;
  width?: string;
  type?: 'text' | 'number' | 'email' | 'select';
  options?: Array<{ value: string; label: string }>;
  formatter?: (value: any) => string;
}

interface DataTableProps<T = Record<string, unknown>> {
  columns: Column[];
  data: T[];
  onRowAction?: (action: string, row: T, index: number) => void;
  onCellEdit?: (rowIndex: number, columnKey: string, value: any) => Promise<void>;
  onSort?: (column: string, direction: 'asc' | 'desc') => void;
  searchable?: boolean;
  searchValue?: string;
  searchPlaceholder?: string;
  onSearch?: (query: string) => void;
  selectable?: boolean;
  selectedRows?: number[];
  onSelectionChange?: (selectedRows: number[]) => void;
  loading?: boolean;
  pagination?: {
    currentPage: number;
    totalPages: number;
    total: number;
    perPage: number;
    onPageChange: (page: number) => void;
    onPerPageChange?: (perPage: number) => void;
  };
}

export default function DataTable<T = Record<string, unknown>>({ 
  columns, 
  data, 
  onRowAction,
  onCellEdit,
  onSort,
  searchable = false,
  searchValue = '',
  searchPlaceholder = 'Search...',
  onSearch,
  selectable = false,
  selectedRows = [],
  onSelectionChange,
  loading = false,
  pagination 
}: DataTableProps<T>) {
  const [sortColumn, setSortColumn] = useState<string>('');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');
  const [editingCell, setEditingCell] = useState<{ row: number; column: string } | null>(null);
  const [editValue, setEditValue] = useState<any>('');
  const [savingCell, setSavingCell] = useState<{ row: number; column: string } | null>(null);

  const handleSort = useCallback((columnKey: string) => {
    const newDirection = sortColumn === columnKey && sortDirection === 'asc' ? 'desc' : 'asc';
    setSortColumn(columnKey);
    setSortDirection(newDirection);
    
    if (onSort) {
      onSort(columnKey, newDirection);
    }
  }, [sortColumn, sortDirection, onSort]);

  const handleCellEdit = useCallback((rowIndex: number, columnKey: string, currentValue: any) => {
    setEditingCell({ row: rowIndex, column: columnKey });
    setEditValue(currentValue);
  }, []);

  const handleCellSave = useCallback(async (rowIndex: number, columnKey: string) => {
    if (!onCellEdit) return;

    setSavingCell({ row: rowIndex, column: columnKey });
    
    try {
      await onCellEdit(rowIndex, columnKey, editValue);
      setEditingCell(null);
      setEditValue('');
    } catch (error) {
      console.error('Failed to save cell:', error);
      // Keep editing mode on error
    } finally {
      setSavingCell(null);
    }
  }, [onCellEdit, editValue]);

  const handleCellCancel = useCallback(() => {
    setEditingCell(null);
    setEditValue('');
  }, []);

  const handleSelectAll = useCallback(() => {
    if (!onSelectionChange) return;
    
    const allRowIds = data.map((_, index) => index);
    const isAllSelected = selectedRows.length === data.length;
    
    onSelectionChange(isAllSelected ? [] : allRowIds);
  }, [data, selectedRows, onSelectionChange]);

  const handleRowSelect = useCallback((rowIndex: number) => {
    if (!onSelectionChange) return;
    
    const isSelected = selectedRows.includes(rowIndex);
    const newSelection = isSelected
      ? selectedRows.filter(id => id !== rowIndex)
      : [...selectedRows, rowIndex];
    
    onSelectionChange(newSelection);
  }, [selectedRows, onSelectionChange]);

  const renderCell = useCallback((row: T, column: Column, rowIndex: number) => {
    const value = (row as Record<string, unknown>)[column.key];
    const isEditing = editingCell?.row === rowIndex && editingCell?.column === column.key;
    const isSaving = savingCell?.row === rowIndex && savingCell?.column === column.key;

    if (column.key === 'actions') {
      return (
        <div className="flex gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => onRowAction?.('edit', row, rowIndex)}
          >
            Edit
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => onRowAction?.('delete', row, rowIndex)}
            className="text-red-400 border-red-400 hover:bg-red-400 hover:text-white"
          >
            Delete
          </Button>
        </div>
      );
    }

    if (isEditing && column.editable) {
      if (column.type === 'select' && column.options) {
        return (
          <div className="flex gap-2 items-center">
            <select
              value={editValue}
              onChange={(e) => setEditValue(e.target.value)}
              className="bg-[#283039] text-white rounded px-2 py-1 text-sm border-none focus:outline-none focus:ring-1 focus:ring-[#0d80f2]"
              disabled={isSaving}
            >
              {column.options.map(option => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
            <Button
              size="sm"
              onClick={() => handleCellSave(rowIndex, column.key)}
              disabled={isSaving}
            >
              {isSaving ? '...' : '✓'}
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={handleCellCancel}
              disabled={isSaving}
            >
              ✕
            </Button>
          </div>
        );
      } else {
        return (
          <div className="flex gap-2 items-center">
            <input
              type={column.type || 'text'}
              value={editValue}
              onChange={(e) => setEditValue(e.target.value)}
              className="bg-[#283039] text-white rounded px-2 py-1 text-sm border-none focus:outline-none focus:ring-1 focus:ring-[#0d80f2] w-full"
              disabled={isSaving}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  handleCellSave(rowIndex, column.key);
                } else if (e.key === 'Escape') {
                  handleCellCancel();
                }
              }}
              autoFocus
            />
            <Button
              size="sm"
              onClick={() => handleCellSave(rowIndex, column.key)}
              disabled={isSaving}
            >
              {isSaving ? '...' : '✓'}
            </Button>
            <Button
              variant="outline"
              size="sm"
              onClick={handleCellCancel}
              disabled={isSaving}
            >
              ✕
            </Button>
          </div>
        );
      }
    }

    const displayValue = column.formatter ? column.formatter(value) : String(value || '');
    
    return (
      <div
        className={`${column.editable ? 'cursor-pointer hover:bg-[#283039] rounded px-2 py-1' : ''}`}
        onClick={() => column.editable && handleCellEdit(rowIndex, column.key, value)}
      >
        {displayValue}
        {column.editable && (
          <span className="ml-2 text-[#9cabba] text-xs opacity-0 group-hover:opacity-100">
            Click to edit
          </span>
        )}
      </div>
    );
  }, [editingCell, editValue, savingCell, onRowAction, handleCellEdit, handleCellSave, handleCellCancel]);

  const sortedData = onSort ? data : [...data].sort((a, b) => {
    if (!sortColumn) return 0;
    
    const aValue = (a as Record<string, unknown>)[sortColumn];
    const bValue = (b as Record<string, unknown>)[sortColumn];
    
    if (typeof aValue === 'string' && typeof bValue === 'string') {
      return sortDirection === 'asc' 
        ? aValue.localeCompare(bValue)
        : bValue.localeCompare(aValue);
    } else if (typeof aValue === 'number' && typeof bValue === 'number') {
      return sortDirection === 'asc' ? aValue - bValue : bValue - aValue;
    }
    
    return 0;
  });

  if (loading) {
    return (
      <div className="px-4 py-3">
        <div className="flex items-center justify-center h-64">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-[#0d80f2]"></div>
          <span className="ml-3 text-[#9cabba]">Loading...</span>
        </div>
      </div>
    );
  }

  return (
    <div className="px-4 py-3">
      {searchable && onSearch && (
        <div className="mb-4 flex items-center gap-3">
          <div className="flex-1">
            <Input
              placeholder={searchPlaceholder}
              variant="search"
              value={searchValue}
              onChange={(e) => onSearch(e.target.value)}
            />
          </div>
          {searchValue && (
            <div className="flex items-center gap-2 text-sm text-[#9cabba]">
              <span>Searching for: "{searchValue}"</span>
              <Button
                variant="outline"
                size="sm"
                onClick={() => onSearch('')}
              >
                Clear
              </Button>
            </div>
          )}
        </div>
      )}
      
      <div className="flex overflow-hidden rounded-lg border border-[#3b4754] bg-[#111418]">
        <table className="flex-1">
          <thead>
            <tr className="bg-[#1b2127]">
              {selectable && (
                <th className="px-4 py-3 w-12">
                  <input
                    type="checkbox"
                    checked={selectedRows.length === data.length && data.length > 0}
                    onChange={handleSelectAll}
                    className="h-4 w-4 rounded border-[#3b4754] border-2 bg-transparent text-[#0d80f2] checked:bg-[#0d80f2] checked:border-[#0d80f2]"
                  />
                </th>
              )}
              {columns.map((column) => (
                <th
                  key={column.key}
                  className={`px-4 py-3 text-left text-white text-sm font-medium leading-normal ${
                    column.width || 'w-auto'
                  } ${
                    column.sortable ? 'cursor-pointer hover:bg-[#283039]' : ''
                  }`}
                  onClick={() => column.sortable && handleSort(column.key)}
                >
                  <div className="flex items-center gap-2">
                    {column.label}
                    {column.sortable && sortColumn === column.key && (
                      <span className="text-[#0d80f2]">
                        {sortDirection === 'asc' ? '↑' : '↓'}
                      </span>
                    )}
                    {column.editable && (
                      <span className="text-[#9cabba] text-xs">✎</span>
                    )}
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {sortedData.length === 0 ? (
              <tr>
                <td 
                  colSpan={columns.length + (selectable ? 1 : 0)} 
                  className="h-32 text-center text-[#9cabba]"
                >
                  No data available
                </td>
              </tr>
            ) : (
              sortedData.map((row, index) => (
                <tr key={index} className="border-t border-t-[#3b4754] group hover:bg-[#1b2127]">
                  {selectable && (
                    <td className="px-4 py-2 w-12">
                      <input
                        type="checkbox"
                        checked={selectedRows.includes(index)}
                        onChange={() => handleRowSelect(index)}
                        className="h-4 w-4 rounded border-[#3b4754] border-2 bg-transparent text-[#0d80f2] checked:bg-[#0d80f2] checked:border-[#0d80f2]"
                      />
                    </td>
                  )}
                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={`h-[72px] px-4 py-2 text-sm font-normal leading-normal ${
                        column.width || 'w-auto'
                      } ${
                        column.key === 'actions' 
                          ? 'text-[#0d80f2] font-bold tracking-[0.015em]' 
                          : 'text-[#9cabba]'
                      }`}
                    >
                      {renderCell(row, column, index)}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
      
      {pagination && (
        <div className="flex justify-between items-center mt-4">
          <div className="flex items-center gap-4">
            <div className="text-[#9cabba] text-sm">
              Showing {((pagination.currentPage - 1) * pagination.perPage) + 1} to{' '}
              {Math.min(pagination.currentPage * pagination.perPage, pagination.total)} of{' '}
              {pagination.total} entries
            </div>
            {pagination.onPerPageChange && (
              <div className="flex items-center gap-2">
                <span className="text-[#9cabba] text-sm">Show:</span>
                <select
                  value={pagination.perPage}
                  onChange={(e) => pagination.onPerPageChange?.(Number(e.target.value))}
                  className="bg-[#283039] text-white rounded px-2 py-1 text-sm border-none focus:outline-none focus:ring-1 focus:ring-[#0d80f2]"
                >
                  <option value={10}>10</option>
                  <option value={20}>20</option>
                  <option value={50}>50</option>
                  <option value={100}>100</option>
                </select>
              </div>
            )}
          </div>
          
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.currentPage === 1}
              onClick={() => pagination.onPageChange(pagination.currentPage - 1)}
            >
              Previous
            </Button>
            
            {/* Page numbers */}
            {Array.from({ length: Math.min(5, pagination.totalPages) }, (_, i) => {
              const pageNum = Math.max(1, pagination.currentPage - 2) + i;
              if (pageNum > pagination.totalPages) return null;
              
              return (
                <Button
                  key={pageNum}
                  variant={pageNum === pagination.currentPage ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => pagination.onPageChange(pageNum)}
                >
                  {pageNum}
                </Button>
              );
            })}
            
            <Button
              variant="outline"
              size="sm"
              disabled={pagination.currentPage === pagination.totalPages}
              onClick={() => pagination.onPageChange(pagination.currentPage + 1)}
            >
              Next
            </Button>
          </div>
        </div>
      )}
      
      {selectable && selectedRows.length > 0 && (
        <div className="mt-4 p-3 bg-[#0d80f2]/10 border border-[#0d80f2]/20 rounded-lg">
          <div className="flex items-center justify-between">
            <span className="text-white text-sm">
              {selectedRows.length} row{selectedRows.length !== 1 ? 's' : ''} selected
            </span>
            <div className="flex gap-2">
              <Button size="sm" onClick={() => onRowAction?.('bulk-edit', selectedRows as any, -1)}>
                Bulk Edit
              </Button>
              <Button 
                variant="outline" 
                size="sm" 
                onClick={() => onRowAction?.('bulk-delete', selectedRows as any, -1)}
                className="text-red-400 border-red-400 hover:bg-red-400 hover:text-white"
              >
                Delete Selected
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
