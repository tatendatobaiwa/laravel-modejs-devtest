'use client';

import { useState, useCallback } from 'react';
import Button from './Button';
import Input from './Input';
import Modal from './Modal';
import { SearchPreset, useSearchPresets } from '@/hooks/useSearchPresets';

interface SearchPresetsProps {
  currentSearch: string;
  currentFilters: Record<string, any>;
  onApplyPreset: (preset: SearchPreset) => void;
  onSavePreset?: (name: string, description?: string) => void;
  className?: string;
}

export default function SearchPresets({
  currentSearch,
  currentFilters,
  onApplyPreset,
  onSavePreset,
  className = '',
}: SearchPresetsProps) {
  const [showModal, setShowModal] = useState(false);
  const [showSaveModal, setShowSaveModal] = useState(false);
  const [presetName, setPresetName] = useState('');
  const [presetDescription, setPresetDescription] = useState('');
  const [searchQuery, setSearchQuery] = useState('');

  const presets = useSearchPresets();

  const handleApplyPreset = useCallback((preset: SearchPreset) => {
    presets.usePreset(preset.id);
    onApplyPreset(preset);
    setShowModal(false);
  }, [presets, onApplyPreset]);

  const handleSavePreset = useCallback(() => {
    if (!presetName.trim()) return;

    const preset = presets.createPreset(
      presetName.trim(),
      currentSearch,
      currentFilters,
      presetDescription.trim() || undefined
    );

    onSavePreset?.(presetName.trim(), presetDescription.trim() || undefined);
    
    setPresetName('');
    setPresetDescription('');
    setShowSaveModal(false);
  }, [presets, presetName, presetDescription, currentSearch, currentFilters, onSavePreset]);

  const handleDeletePreset = useCallback((presetId: string) => {
    if (confirm('Are you sure you want to delete this search preset?')) {
      presets.deletePreset(presetId);
    }
  }, [presets]);

  const filteredPresets = presets.searchPresets(searchQuery);
  const popularPresets = presets.getPopularPresets(3);
  const recentPresets = presets.getRecentPresets(3);

  const hasCurrentSearch = currentSearch.trim() || Object.keys(currentFilters).length > 0;
  const currentPreset = presets.presets.find(preset =>
    preset.search === currentSearch &&
    JSON.stringify(preset.filters) === JSON.stringify(currentFilters)
  );

  return (
    <>
      <div className={`flex items-center gap-2 ${className}`}>
        {/* Quick access to popular presets */}
        {popularPresets.length > 0 && (
          <div className="flex items-center gap-2">
            <span className="text-[#9cabba] text-sm">Quick:</span>
            {popularPresets.map(preset => (
              <Button
                key={preset.id}
                variant="outline"
                size="sm"
                onClick={() => handleApplyPreset(preset)}
                title={preset.description || `Used ${preset.useCount} times`}
              >
                {preset.name}
              </Button>
            ))}
          </div>
        )}

        {/* Manage presets button */}
        <Button
          variant="outline"
          size="sm"
          onClick={() => setShowModal(true)}
        >
          <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
          </svg>
          Presets ({presets.presets.length})
        </Button>

        {/* Save current search */}
        {hasCurrentSearch && !currentPreset && (
          <Button
            variant="outline"
            size="sm"
            onClick={() => setShowSaveModal(true)}
          >
            <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            Save Search
          </Button>
        )}

        {/* Current preset indicator */}
        {currentPreset && (
          <div className="flex items-center gap-2 px-2 py-1 bg-[#0d80f2]/20 text-[#0d80f2] rounded text-sm">
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
            </svg>
            <span>{currentPreset.name}</span>
          </div>
        )}
      </div>

      {/* Presets Management Modal */}
      <Modal
        isOpen={showModal}
        onClose={() => setShowModal(false)}
        title="Search Presets"
        size="lg"
        actions={
          <>
            <Button variant="outline" onClick={presets.exportPresets}>
              Export
            </Button>
            <Button variant="outline" onClick={() => setShowModal(false)}>
              Close
            </Button>
          </>
        }
      >
        <div className="space-y-6">
          {/* Search presets */}
          <div>
            <Input
              placeholder="Search presets..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              variant="search"
            />
          </div>

          {/* Popular presets */}
          {popularPresets.length > 0 && (
            <div>
              <h4 className="text-white text-sm font-medium mb-3">Most Used</h4>
              <div className="space-y-2">
                {popularPresets.map(preset => (
                  <div
                    key={preset.id}
                    className="flex items-center justify-between p-3 bg-[#283039] rounded-lg"
                  >
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <h5 className="text-white text-sm font-medium">{preset.name}</h5>
                        <span className="text-[#9cabba] text-xs">
                          Used {preset.useCount} times
                        </span>
                      </div>
                      {preset.description && (
                        <p className="text-[#9cabba] text-xs mt-1">{preset.description}</p>
                      )}
                      <div className="flex items-center gap-4 mt-2 text-xs text-[#9cabba]">
                        {preset.search && (
                          <span>Search: "{preset.search}"</span>
                        )}
                        {Object.keys(preset.filters).length > 0 && (
                          <span>{Object.keys(preset.filters).length} filters</span>
                        )}
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        onClick={() => handleApplyPreset(preset)}
                      >
                        Apply
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleDeletePreset(preset.id)}
                        className="text-red-400 border-red-400 hover:bg-red-400 hover:text-white"
                      >
                        Delete
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* All presets */}
          <div>
            <div className="flex items-center justify-between mb-3">
              <h4 className="text-white text-sm font-medium">
                All Presets ({filteredPresets.length})
              </h4>
              {presets.presets.length > 0 && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    if (confirm('Are you sure you want to delete all presets?')) {
                      presets.clearPresets();
                    }
                  }}
                  className="text-red-400 border-red-400 hover:bg-red-400 hover:text-white"
                >
                  Clear All
                </Button>
              )}
            </div>

            {filteredPresets.length === 0 ? (
              <div className="text-center py-8">
                <p className="text-[#9cabba] text-sm">
                  {searchQuery ? 'No presets match your search' : 'No saved presets yet'}
                </p>
              </div>
            ) : (
              <div className="space-y-2 max-h-64 overflow-y-auto">
                {filteredPresets.map(preset => (
                  <div
                    key={preset.id}
                    className="flex items-center justify-between p-3 bg-[#283039] rounded-lg"
                  >
                    <div className="flex-1">
                      <div className="flex items-center gap-2">
                        <h5 className="text-white text-sm font-medium">{preset.name}</h5>
                        {preset.useCount > 0 && (
                          <span className="text-[#9cabba] text-xs">
                            {preset.useCount}x
                          </span>
                        )}
                      </div>
                      {preset.description && (
                        <p className="text-[#9cabba] text-xs mt-1">{preset.description}</p>
                      )}
                      <div className="flex items-center gap-4 mt-2 text-xs text-[#9cabba]">
                        <span>
                          Created {new Date(preset.createdAt).toLocaleDateString()}
                        </span>
                        {preset.lastUsed && (
                          <span>
                            Last used {new Date(preset.lastUsed).toLocaleDateString()}
                          </span>
                        )}
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        size="sm"
                        onClick={() => handleApplyPreset(preset)}
                      >
                        Apply
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleDeletePreset(preset.id)}
                        className="text-red-400 border-red-400 hover:bg-red-400 hover:text-white"
                      >
                        Delete
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>
      </Modal>

      {/* Save Preset Modal */}
      <Modal
        isOpen={showSaveModal}
        onClose={() => setShowSaveModal(false)}
        title="Save Search Preset"
        size="md"
        actions={
          <>
            <Button variant="outline" onClick={() => setShowSaveModal(false)}>
              Cancel
            </Button>
            <Button 
              onClick={handleSavePreset}
              disabled={!presetName.trim()}
            >
              Save Preset
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <p className="text-[#9cabba] text-sm">
            Save your current search and filters as a preset for quick access later.
          </p>

          <Input
            label="Preset Name"
            value={presetName}
            onChange={(e) => setPresetName(e.target.value)}
            placeholder="e.g., High Salary Engineers"
            required
          />

          <Input
            label="Description (Optional)"
            value={presetDescription}
            onChange={(e) => setPresetDescription(e.target.value)}
            placeholder="Brief description of this search..."
          />

          {/* Preview */}
          <div className="border-t border-[#3b4754] pt-4">
            <h4 className="text-white text-sm font-medium mb-2">Preview:</h4>
            <div className="space-y-2 text-xs text-[#9cabba]">
              {currentSearch && (
                <div>Search: "{currentSearch}"</div>
              )}
              {Object.keys(currentFilters).length > 0 && (
                <div>
                  Filters: {Object.entries(currentFilters).map(([key, value]) => 
                    `${key}=${value}`
                  ).join(', ')}
                </div>
              )}
            </div>
          </div>
        </div>
      </Modal>
    </>
  );
}