// messages.js - Handles specific logic for the messaging page.
// This includes dynamically showing/hiding fields, updating a character counter,
// and auto-filling the message subject based on the selected event.

document.addEventListener('DOMContentLoaded', function() {
    // Initialize functions on page load.
    toggleSpecificFields();
    toggleEventField();
    updateCharacterCount();

    // Attach event listeners to the message form elements.
    const recipientTypeSelect = document.getElementById('recipient_type');
    const eventSelect = document.getElementById('event_select');
    const messageContent = document.getElementById('message_content');

    if (recipientTypeSelect) {
        recipientTypeSelect.addEventListener('change', toggleSpecificFields);
    }
    if (eventSelect) {
        eventSelect.addEventListener('change', toggleEventField);
    }
    if (messageContent) {
        messageContent.addEventListener('input', updateCharacterCount);
    }
});

/**
 * Toggles the visibility of specific recipient fields based on the selected recipient type.
 */
function toggleSpecificFields() {
    const recipientType = document.getElementById('recipient_type').value;
    const specificClassDiv = document.getElementById('specific_class_div');
    const specificTeacherDiv = document.getElementById('specific_teacher_div');
    const specificStudentDiv = document.getElementById('specific_student_div');
    const specificParentDiv = document.getElementById('specific_parent_div');

    if (specificClassDiv) {
        specificClassDiv.style.display = recipientType === 'specific_class' ? 'block' : 'none';
    }
    if (specificTeacherDiv) {
        specificTeacherDiv.style.display = recipientType === 'specific_teacher' ? 'block' : 'none';
    }
    if (specificStudentDiv) {
        specificStudentDiv.style.display = recipientType === 'specific_student' ? 'block' : 'none';
    }
    if (specificParentDiv) {
        specificParentDiv.style.display = recipientType === 'specific_parent' ? 'block' : 'none';
    }
}

/**
 * Toggles the visibility of the event field and updates the message subject.
 */
function toggleEventField() {
    const eventType = document.getElementById('message_type').value;
    const eventField = document.getElementById('event_field');
    const eventPreview = document.getElementById('event_preview');

    if (eventField) {
        eventField.style.display = eventType === 'event_reminder' ? 'block' : 'none';
    }

    // Update the event preview when an event is selected
    const selectedEvent = document.getElementById('event_select');
    if (eventPreview && selectedEvent && selectedEvent.value) {
        const selectedOption = selectedEvent.options[selectedEvent.selectedIndex];
        document.getElementById('event_title_preview').textContent = selectedOption.text.split(' (')[0];
        document.getElementById('event_date_preview').textContent = 
            'Date: ' + new Date(selectedOption.getAttribute('data-date')).toLocaleDateString();
        document.getElementById('event_type_preview').textContent = 
            'Type: ' + selectedOption.getAttribute('data-type');
        eventPreview.style.display = 'block';
        
        // Auto-fill subject if empty
        const subjectInput = document.getElementById('subject');
        if (subjectInput && !subjectInput.value) {
            subjectInput.value = 'Reminder: ' + selectedOption.text.split(' (')[0];
        }
    } else if (eventPreview) {
        eventPreview.style.display = 'none';
    }
}

/**
 * Updates the character count for the message content and provides a warning.
 */
function updateCharacterCount() {
    const messageContent = document.getElementById('message_content');
    const charCountSpan = document.getElementById('char_count');
    if (!messageContent || !charCountSpan) return;
    
    const content = messageContent.value;
    charCountSpan.textContent = content.length;
    
    // Warn if message is getting long for SMS
    if (content.length > 140) {
        charCountSpan.style.color = '#e74c3c';
    } else {
        charCountSpan.style.color = '#6c757d';
    }
}
