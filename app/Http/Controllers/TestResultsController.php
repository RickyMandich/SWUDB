<?php

namespace App\Http\Controllers;

use App\Models\TestResult;
use Illuminate\Http\Request;

class TestResultsController extends Controller
{
    /**
     * Display a listing of test results
     */
    public function index()
    {
        $results = TestResult::orderBy('created_at', 'desc')->paginate(20);
        $breadcrumbs = [
            ['text' => 'Admin', 'url' => route('admin.dashboard')],
            ['text' => 'History Test', 'url' => null],
        ];

        return view('admin.tests.index', compact('results', 'breadcrumbs'));
    }

    /**
     * Show detailed output of a specific test run
     */
    public function show($id)
    {
        $result = TestResult::findOrFail($id);
        $breadcrumbs = [
            ['text' => 'Admin', 'url' => route('admin.dashboard')],
            ['text' => 'History Test', 'url' => route('admin.tests.index')],
            ['text' => 'Dettaglio Run', 'url' => null],
        ];

        return view('admin.tests.show', compact('result', 'breadcrumbs'));
    }
}
