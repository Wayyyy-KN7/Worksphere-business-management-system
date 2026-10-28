<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerDocumentController extends Controller
{
    public function index(Customer $customer)
    {
        $this->authorize('view', $customer);

        $documents = $customer->documents()
            ->with(['uploader', 'latestVersion'])
            ->latest()
            ->get();

        return view('crm.customers.documents.index', compact('customer', 'documents'));
    }

    public function store(Request $request, Customer $customer)
    {
        $this->authorize('update', $customer);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'document_type' => 'required|string',
            'file' => 'required|file|max:10240',
        ]);

        $document = new Document([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'document_type' => $validated['document_type'],
            'uploaded_by' => Auth::id(),
            'department_id' => Auth::user()->department_id,
            'access_level' => 'DEPARTMENT',
            'status' => 'PUBLISHED',
        ]);

        $customer->documents()->save($document);

        // Upload file
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('documents/customers', $fileName, 'public');

            $document->versions()->create([
                'version_number' => '1.0',
                'file_path' => $filePath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'change_description' => 'Initial upload',
                'uploaded_by' => Auth::id(),
            ]);
        }

        $document->logActivity('CREATED', 'Document attached to customer');

        return back()->with('success', 'Document uploaded successfully');
    }

    public function destroy(Customer $customer, Document $document)
    {
        $this->authorize('update', $customer);

        if ($document->documentable_id !== $customer->id) {
            abort(403);
        }

        $document->delete();

        return back()->with('success', 'Document deleted successfully');
    }
}