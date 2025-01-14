<?php

namespace App\Http\Controllers;

use App\Models\ContactForm;
use App\Mail\ContactFormMail;
use App\Traits\HandlesApiResponse;
use Illuminate\Support\Facades\Mail;
use App\Http\Requests\ContactFormRequest;

class ContactFormController extends Controller
{
    use HandlesApiResponse;

    public function index()
    {
        return $this->safeCall(function () {
            $data = ContactForm::all();

            return $this->successResponse(
                'Contact form data',
                ['data' => $data]
            );
        });
    }

    public function submit(ContactFormRequest $request)
    {
        return $this->safeCall(function () use ($request) {
            $data = ContactForm::create($request->all());

            if (!$data || !$data->exists) {
                return $this->errorResponse(
                    'Failed to submit your query',
                    500
                );
            }

            return $this->successResponse(
                'Your query has been submitted successfully!',
                ['data' => $data]
            );
        });
    }

    public function submitSend(ContactFormRequest $request)
    {
        return $this->safeCall(function () use ($request) {
            // Save the contact form data to the database
            $data = ContactForm::create($request->all());

            // Send the email
            Mail::to($request->email)->send(new ContactFormMail($data));

            return $this->successResponse(
                'Form submitted successfully and email sent to the provided address.',
                ['contact_form' => $data]
            );
        });
    }

    public function show($id)
    {
        return $this->safeCall(function () use ($id) {
            $data = ContactForm::find($id);

            if (!$data || !$data->exists) {
                return $this->errorResponse(
                    'No data found',
                    404
                );
            }

            return $this->successResponse(
                'Contact form data',
                ['data' => $data]
            );
        });
    }

    public function update(ContactFormRequest $request, $id)
    {
        return $this->safeCall(function () use ($request, $id) {
            // Find the contact entry by ID
            $contact = ContactForm::find($id);

            // Check if the entry exists
            if (!$contact) {
                return $this->errorResponse(
                    'No contact found with the given ID',
                    404
                );
            }

            // Update the contact entry
            $contact->update($request->all());

            return $this->successResponse(
                'Contact updated successfully',
                ['data' => $contact]
            );
        });
    }

    public function destroy($id)
    {
        return $this->safeCall(function () use ($id) {
            $data = ContactForm::find($id);

            // Check if the data exists
            if (!$data) {
                return $this->errorResponse(
                    'No data found with the given ID',
                    404
                );
            }

            // Delete the data
            $data->delete();

            return $this->successResponse(
                'Contact form data deleted successfully',
                ['data' => $data]
            );
        });
    }
}
