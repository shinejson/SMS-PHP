<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Student Billing Form</title>
    <?php include 'favicon.php'; ?>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            width: 95%;
            padding: 8px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 14px;
        }

        .sub-field-group {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .sub-field-group input[type="text"],
        .sub-field-group input[type="number"] {
            flex: 1;
            width: 10%;
            padding: 6px;
        }

        .add-btn {
            margin-top: 5px;
            background: #3498db;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .submit-btn {
            margin-top: 20px;
            background: #2ecc71;
            color: white;
            border: none;
            padding: 10px 16px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
        }

        .close-btn {
            margin-left: 10px;
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 16px;
            font-size: 16px;
            border-radius: 6px;
            cursor: pointer;
        }

        #billingMessage {
            margin-top: 15px;
            padding: 10px;
            border-radius: 6px;
            font-weight: bold;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container" id="billingForm">
        <h2>Add Student Billing</h2>

        <form id="billingFormElement" target="_blank">
            <label for="payment_type">Payment Type:</label>
            <select name="payment_type" id="payment_type" required>
                <option value="">--Select Type--</option>
                <option value="Tuition">Tuition</option>
                <option value="PTA">PTA</option>
                <option value="Extra Class">Extra Class</option>
                <option value="Others">Others</option>
            </select>

            <div id="tuitionFields" class="hidden">
            <div id="tuitionSubFields">
                <!-- Default tuition row -->
                <div class="sub-field-group">
                    <input type="text" name="sub_field_name[]" placeholder="e.g. Library Fee" required>
                    <input type="number" name="sub_field_amount[]" class="sub-fee-amount" placeholder="Amount" step="0.01" required>
                </div>
            </div>

            <button type="button" class="add-btn add-sub-fee" onclick="addTuitionField()">+ Add Another</button>
            </div>
                
    
            <div id="simpleFieldGroup" class="hidden">
                <label for="simple_amount">Amount:</label>
                <input type="number" id="amount" name="amount" placeholder="Enter amount" readonly>
            </div>

            <label for="term_id">Term:</label>
            <select name="term_id" id="term_id" required>
                <option value="">--Select Term--</option>
                <option value="1">Term 1</option>
                <option value="2">Term 2</option>
                <option value="3">Term 3</option>
            </select>

            <label for="academic_year">Academic Year:</label>
            <input type="text" name="academic_year" id="academic_year" placeholder="e.g. 2025/2026" required>

            <label for="due_date">Due Date:</label>
            <input type="date" name="due_date" id="due_date" required>

            <label for="description">Description (optional):</label>
            <textarea name="description" id="description" rows="3" placeholder="Description..."></textarea>

            <button type="submit" class="submit-btn">Submit</button>
            <button type="button" class="close-btn" onclick="closeBillingForm()">Close</button>
        </form>

        <div id="billingMessage"></div>
    </div>

  <script>
    const paymentType = document.getElementById('payment_type');
    const tuitionFields = document.getElementById('tuitionFields');
    const simpleFieldGroup = document.getElementById('simpleFieldGroup');
    const tuitionContainer = document.getElementById('tuitionSubFields');

    // Show/Hide tuition vs simple field
    paymentType.addEventListener('change', function () {
        const selected = this.value;
        if (selected === 'Tuition') {
            tuitionFields.classList.remove('hidden');
            simpleFieldGroup.classList.add('hidden');
        } else if (selected === 'PTA' || selected === 'Extra Class' || selected === 'Others') {
            tuitionFields.classList.add('hidden');
            simpleFieldGroup.classList.remove('hidden');
        } else {
            tuitionFields.classList.add('hidden');
            simpleFieldGroup.classList.add('hidden');
        }
    });

    // Add new tuition sub-field row
function addTuitionField() {
    const div = document.createElement('div');
    div.className = 'sub-field-group sub-fee-row';
    div.innerHTML = `
        <input type="text" name="sub_field_name[]" placeholder="e.g. Sports Fee" required>
        <input type="number" name="sub_field_amount[]" class="sub-fee-amount" placeholder="Amount" step="0.01" required>
        <button type="button" class="remove-sub-fee add-btn">Remove</button>
    `;
    tuitionContainer.appendChild(div);
}


    // Remove a sub-field row
    tuitionContainer.addEventListener('click', function (e) {
        if (e.target.classList.contains('removeSubField')) {
            e.target.parentElement.remove();
        }
    });

    // Close billing form
    function closeBillForm() {
        document.getElementById('billingForm').classList.add('hidden');
    }

    // Submit form
    document.getElementById('billingFormElement').addEventListener('submit', function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('insert_billing.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            const msg = document.getElementById('billingMessage');
            msg.innerText = data.message;
            msg.style.background = data.success ? '#d4edda' : '#f8d7da';
            msg.style.color = data.success ? '#155724' : '#721c24';

            // Reset form & hide fields
            this.reset();
            tuitionFields.classList.add('hidden');
            simpleFieldGroup.classList.add('hidden');
            tuitionContainer.innerHTML = ''; // clear all extra tuition rows
        })
        .catch(err => {
            document.getElementById('billingMessage').innerText = 'Error submitting form.';
        });
    });

    // Function to recalculate tuition total
function calculateTuitionTotal() {
    let total = 0;
    document.querySelectorAll('.sub-fee-amount').forEach(input => {
        let value = parseFloat(input.value) || 0;
        total += value;
    });
    document.getElementById('amount').value = total.toFixed(2);
}

// Listen for changes on sub-fee amount fields
document.addEventListener('input', function (e) {
    if (e.target.classList.contains('sub-fee-amount')) {
        calculateTuitionTotal();
    }
});

// Handle new sub-fee row addition
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('add-sub-fee')) {
        setTimeout(() => {
            calculateTuitionTotal(); // recalc after adding
        }, 50);
    }
});

document.addEventListener('click', function (e) {
    if (e.target.classList.contains('add-sub-fee')) {
        addTuitionField(); // Add new row
        calculateTuitionTotal(); // Recalculate
    }
});


// Handle sub-fee row deletion
document.addEventListener('click', function (e) {
    if (e.target.classList.contains('remove-sub-fee')) {
        e.target.closest('.sub-fee-row').remove();
        calculateTuitionTotal(); // recalc immediately after deleting
    }
});

</script>

</body>
</html>
