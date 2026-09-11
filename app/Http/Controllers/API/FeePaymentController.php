<?php

namespace App\Http\Controllers\API;

use App\Models\FeeItem;
use App\Models\FeePayment;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FeePaymentController extends BaseController
{
    private function allowed(Request $request): bool
    {
        return in_array($request->user()->role, [
            'headmaster',
            'accountant',
        ], true);
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to view fee payments.',
                null,
                403
            );
        }

        $query = FeePayment::query()
            ->with([
                'student:id,student_id,first_name,last_name',
                'schoolClass:id,name,code',
                'feeItem:id,name,code,amount,academic_year,term',
                'receiver:id,name',
            ])
            ->latest('payment_date')
            ->latest('id');

        if ($request->filled('school_class_id')) {
            $query->where(
                'school_class_id',
                $request->integer('school_class_id')
            );
        }

        if ($request->filled('student_id')) {
            $query->where(
                'student_id',
                $request->integer('student_id')
            );
        }

        if ($request->filled('fee_item_id')) {
            $query->where(
                'fee_item_id',
                $request->integer('fee_item_id')
            );
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->sendResponse(
            $query->paginate(
                min($request->integer('per_page', 50), 100)
            ),
            'Fee payments retrieved successfully.'
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to record fee payments.',
                null,
                403
            );
        }

        $validator = Validator::make($request->all(), [
            'student_id' => [
                'required',
                'integer',
                'exists:students,id',
            ],
            'school_class_id' => [
                'required',
                'integer',
                'exists:school_classes,id',
            ],
            'fee_item_id' => [
                'required',
                'integer',
                'exists:fee_items,id',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:1',
            ],
            'payment_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'payment_method' => [
                'required',
                Rule::in([
                    'cash',
                    'bank',
                    'mobile_money',
                ]),
            ],
            'reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The fee payment information is invalid.',
                $validator->errors(),
                422
            );
        }

        $student = Student::findOrFail(
            $request->integer('student_id')
        );

        if (
            (int) $student->school_class_id !==
            $request->integer('school_class_id')
        ) {
            return $this->sendError(
                'The student does not belong to the selected class.',
                null,
                422
            );
        }

        $feeItem = FeeItem::query()
            ->whereKey($request->integer('fee_item_id'))
            ->where('status', 'active')
            ->first();

        if (!$feeItem) {
            return $this->sendError(
                'The selected payment item is not active.',
                null,
                422
            );
        }

        if (
            $feeItem->school_class_id !== null &&
            (int) $feeItem->school_class_id !==
            $request->integer('school_class_id')
        ) {
            return $this->sendError(
                'The payment item does not apply to this class.',
                null,
                422
            );
        }

        $payment = DB::transaction(function () use (
            $request,
            $student,
            $feeItem
        ) {
            $lockedItem = FeeItem::query()
                ->lockForUpdate()
                ->findOrFail($feeItem->id);

            $alreadyPaid = FeePayment::query()
                ->where('student_id', $student->id)
                ->where('fee_item_id', $lockedItem->id)
                ->where('status', 'completed')
                ->sum('amount');

            $remaining = max(
                (float) $lockedItem->amount -
                (float) $alreadyPaid,
                0
            );

            $amount = (float) $request->amount;

            if ($remaining <= 0) {
                abort(
                    422,
                    'This payment item is already fully paid.'
                );
            }

            if ($amount > $remaining) {
                abort(
                    422,
                    'The payment exceeds the remaining balance of '
                    . number_format($remaining)
                    . ' RWF.'
                );
            }

            $payment = FeePayment::create([
                'student_id' => $student->id,
                'school_class_id' => $request->integer(
                    'school_class_id'
                ),
                'fee_item_id' => $lockedItem->id,
                'received_by' => $request->user()->id,
                'amount' => $amount,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'reference' => filled($request->reference)
                    ? trim($request->reference)
                    : null,
                'notes' => filled($request->notes)
                    ? trim($request->notes)
                    : null,
                'status' => 'completed',
            ]);

            $payment->update([
                'receipt_number' => sprintf(
                    'CGFK-%s-%06d',
                    now()->format('Ymd'),
                    $payment->id
                ),
            ]);

            return $payment;
        });

        $payment->load([
            'student:id,student_id,first_name,last_name',
            'schoolClass:id,name,code',
            'feeItem:id,name,code,amount,academic_year,term',
            'receiver:id,name',
        ]);

        return $this->sendCreated(
            $payment,
            'Fee payment recorded successfully.'
        );
    }

    public function update(
        Request $request,
        FeePayment $feePayment
    ): JsonResponse {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to update fee payments.',
                null,
                403
            );
        }

        if ($feePayment->status === 'voided') {
            return $this->sendError(
                'A voided payment cannot be updated.',
                null,
                422
            );
        }

        $validator = Validator::make($request->all(), [
            'amount' => [
                'required',
                'numeric',
                'min:1',
            ],
            'payment_date' => [
                'required',
                'date',
                'before_or_equal:today',
            ],
            'payment_method' => [
                'required',
                Rule::in([
                    'cash',
                    'bank',
                    'mobile_money',
                ]),
            ],
            'reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The fee payment information is invalid.',
                $validator->errors(),
                422
            );
        }

        $feeItem = $feePayment->feeItem;

        $otherPayments = FeePayment::query()
            ->where('student_id', $feePayment->student_id)
            ->where('fee_item_id', $feePayment->fee_item_id)
            ->where('status', 'completed')
            ->whereKeyNot($feePayment->id)
            ->sum('amount');

        $maximum = max(
            (float) $feeItem->amount -
            (float) $otherPayments,
            0
        );

        if ((float) $request->amount > $maximum) {
            return $this->sendError(
                'The payment exceeds the available balance of '
                . number_format($maximum)
                . ' RWF.',
                null,
                422
            );
        }

        $feePayment->update([
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'reference' => filled($request->reference)
                ? trim($request->reference)
                : null,
            'notes' => filled($request->notes)
                ? trim($request->notes)
                : null,
        ]);

        return $this->sendResponse(
            $feePayment->fresh()->load([
                'student:id,student_id,first_name,last_name',
                'feeItem:id,name,code,amount',
                'receiver:id,name',
            ]),
            'Fee payment updated successfully.'
        );
    }

    public function destroy(
        Request $request,
        FeePayment $feePayment
    ): JsonResponse {
        if (!$this->allowed($request)) {
            return $this->sendError(
                'You are not allowed to void fee payments.',
                null,
                403
            );
        }

        if ($feePayment->status === 'voided') {
            return $this->sendError(
                'This payment is already voided.',
                null,
                422
            );
        }

        $validator = Validator::make($request->all(), [
            'void_reason' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        if ($validator->fails()) {
            return $this->sendError(
                'The void information is invalid.',
                $validator->errors(),
                422
            );
        }

        $feePayment->update([
            'status' => 'voided',
            'voided_by' => $request->user()->id,
            'voided_at' => now(),
            'void_reason' => $request->void_reason
                ?: 'Voided by finance user',
        ]);

        return $this->sendResponse(
            null,
            'Fee payment voided successfully.'
        );
    }
}
