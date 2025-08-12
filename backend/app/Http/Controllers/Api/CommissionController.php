<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommissionController extends Controller
{
    public function index()
    {
        $commission = Commission::getDefaultCommission();
        
        return response()->json([
            'success' => true,
            'data' => $commission
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $commission = Commission::updateCommission($request->amount);
            
            return response()->json([
                'success' => true,
                'message' => 'Commission updated successfully',
                'data' => $commission
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating commission',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
