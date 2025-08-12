'use client';

import { useState } from 'react';
import Button from './Button';

interface Column {
  key: string;
  label: string;
  sortable?: boolean;
  width?: string;
}

interface DataTableProps<T = Record<string, unknown>> {
  columns: Column[];
  data: T[];
  onRowAction?: (action: string, row: T) => void;
  searchable?: boolean;
  onSearch?: (query: string) => void;
  pagination?: {
    currentPage: number;
    totalPages: number;
    onPageChange: (page: number) => void;
  };
}

export default function DataTable<T = Record<string, unknown>>({ 
  columns, 
  data, 
  onRowAction,
  searchable = false,
  onSearch,
  pagination 
}: DataTableProps<T>) {
  const [sortColumn, setSortColumn] = useState<string>('');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc');

  const handleSort = (columnKey: string) => {
    if (sortColumn === columnKey) {
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
    } else {
      setSortColumn(columnKey);
      setSortDirection('asc');
    }
  };

  const sortedData = [...data].sort((a, b) => {
    if (!sortColumn) return 0;
    
    const aValue = (a as Record<string, unknown>)[sortColumn];
    const bValue = (b as Record<string, unknown>)[sortColumn];
    
    // Handle comparison safely
    if (typeof aValue === 'string' && typeof bValue === 'string') {
      if (aValue < bValue) return sortDirection === 'asc' ? -1 : 1;
      if (aValue > bValue) return sortDirection === 'asc' ? 1 : -1;
    } else if (typeof aValue === 'number' && typeof bValue === 'number') {
      if (aValue < bValue) return sortDirection === 'asc' ? -1 : 1;
      if (aValue > bValue) return sortDirection === 'asc' ? 1 : -1;
    }
    
    return 0;
  });

  return (
    <div className="px-4 py-3">
      {searchable && onSearch && (
        <div className="mb-4">
          <input
            type="text"
            placeholder="Search..."
            className="w-full px-4 py-2 bg-[#283039] text-white rounded-lg border-none focus:outline-none focus:ring-2 focus:ring-[#0d80f2]"
            onChange={(e) => onSearch(e.target.value)}
          />
        </div>
      )}
      
      <div className="flex overflow-hidden rounded-lg border border-[#3b4754] bg-[#111418]">
        <table className="flex-1">
          <thead>
            <tr className="bg-[#1b2127]">
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
                  </div>
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {sortedData.map((row, index) => (
              <tr key={index} className="border-t border-t-[#3b4754]">
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
                    {column.key === 'actions' && onRowAction ? (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => onRowAction('update', row)}
                      >
                        Update
                      </Button>
                    ) : (
                      String((row as Record<string, unknown>)[column.key])
                    )}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      
      {pagination && (
        <div className="flex justify-between items-center mt-4">
          <div className="text-[#9cabba] text-sm">
            Page {pagination.currentPage} of {pagination.totalPages}
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
    </div>
  );
}
