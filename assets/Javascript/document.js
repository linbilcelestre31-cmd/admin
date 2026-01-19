// DOM Elements
const uploadBtn = document.getElementById('uploadBtn');
const uploadModal = document.getElementById('uploadModal');
const fileDetailsModal = document.getElementById('fileDetailsModal');
const cancelUpload = document.getElementById('cancelUpload');
const uploadForm = document.getElementById('uploadForm');
const tabs = document.querySelectorAll('.tab');
const closeButtons = document.querySelectorAll('.close');
const activeFilesContainer = document.getElementById('activeFiles');
const trashFilesContainer = document.getElementById('trashFiles');
const fileDetailsContent = document.getElementById('fileDetailsContent');
const searchInput = document.getElementById('documentSearch') || document.querySelector('.search-box input');
const searchButton = document.querySelector('.search-box button');

// Event Listeners
document.addEventListener('DOMContentLoaded', function () {
    loadActiveFiles();

    if (uploadBtn) {
        uploadBtn.addEventListener('click', function () {
            uploadModal.style.display = 'flex';
        });
    }

    if (cancelUpload) {
        cancelUpload.addEventListener('click', function () {
            uploadModal.style.display = 'none';
        });
    }

    if (uploadForm) {
        uploadForm.addEventListener('submit', function (e) {
            e.preventDefault();
            handleFileUpload();
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            // Add active class to clicked tab
            this.classList.add('active');

            // Hide all tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Show corresponding tab content
            const tabId = this.getAttribute('data-tab');
            document.getElementById(`${tabId}-tab`).classList.add('active');

            // Load appropriate files
            if (tabId === 'active') {
                loadActiveFiles();
            } else if (tabId === 'trash') {
                loadTrashFiles();
            }
        });
    });

    closeButtons.forEach(button => {
        button.addEventListener('click', function () {
            uploadModal.style.display = 'none';
            fileDetailsModal.style.display = 'none';
        });
    });

    // Close modal when clicking outside
    window.addEventListener('click', function (e) {
        if (e.target === uploadModal) {
            uploadModal.style.display = 'none';
        }
        if (e.target === fileDetailsModal) {
            fileDetailsModal.style.display = 'none';
        }
    });

    // Search functionality
    if (searchButton) {
        searchButton.addEventListener('click', function () {
            const searchTerm = searchInput ? searchInput.value.trim() : '';
            if (searchTerm) {
                searchFiles(searchTerm);
            } else {
                loadActiveFiles();
            }
        });
    }

    if (searchInput) {
        searchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                const searchTerm = searchInput.value.trim();
                if (searchTerm) {
                    searchFiles(searchTerm);
                } else {
                    loadActiveFiles();
                }
            }
        });
    }
});

// Functions
async function loadActiveFiles() {
    try {
        const response = await fetch('?api=true&action=active');
        const files = await response.json();
        renderFiles(files, activeFilesContainer, false);
    } catch (error) {
        console.error('Error loading active files:', error);
        activeFilesContainer.innerHTML = '<p>Error loading files. Please try again.</p>';
    }
}

async function loadTrashFiles() {
    try {
        const response = await fetch('?api=true&action=deleted');
        const files = await response.json();
        renderFiles(files, trashFilesContainer, true);
    } catch (error) {
        console.error('Error loading trash files:', error);
        trashFilesContainer.innerHTML = '';
    }
}

async function searchFiles(searchTerm) {
    try {
        const response = await fetch(`?api=true&search=${encodeURIComponent(searchTerm)}`);
        const files = await response.json();
        renderFiles(files, activeFilesContainer, false);
    } catch (error) {
        console.error('Error searching files:', error);
        activeFilesContainer.innerHTML = '<p>Error searching files. Please try again.</p>';
    }
}

function renderFiles(files, container, isTrash = false) {
    container.innerHTML = '';

    if (files.length === 0) {
        container.innerHTML = '<p>No files found.</p>';
        return;
    }

    files.forEach(file => {
        container.appendChild(createFileCard(file, isTrash));
    });
}

function createFileCard(file, isTrash = false) {
    const fileCard = document.createElement('div');
    fileCard.className = 'file-card';
    fileCard.setAttribute('data-id', file.id);

    // Determine file icon based on extension
    const fileExtension = file.name.split('.').pop().toLowerCase();
    let fileIcon = '📄'; // Default icon

    if (fileExtension === 'pdf') fileIcon = '📕';
    else if (['doc', 'docx'].includes(fileExtension)) fileIcon = '📘';
    else if (['xls', 'xlsx'].includes(fileExtension)) fileIcon = '📗';
    else if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) fileIcon = '🖼️';

    fileCard.innerHTML = `
                <div class="file-icon">${fileIcon}</div>
                <div class="file-name">${file.name}</div>
                <div class="file-meta">
                    <div>${file.category}</div>
                    <div>${file.upload_date} • ${file.file_size}</div>
                </div>
                <div class="file-actions">
                    ${isTrash ?
            `<button class="btn btn-success restore-btn">Restore</button>
                         <button class="btn btn-danger delete-btn">Delete Permanently</button>` :
            `<button class="btn btn-primary view-btn">View</button>
                         <button class="btn btn-danger delete-btn">Delete</button>`
        }
                </div>
            `;

    // Add event listeners to buttons
    const viewBtn = fileCard.querySelector('.view-btn');
    const deleteBtn = fileCard.querySelector('.delete-btn');
    const restoreBtn = fileCard.querySelector('.restore-btn');

    if (viewBtn) {
        viewBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            showFileDetails(file.id);
        });
    }

    if (deleteBtn) {
        deleteBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            if (isTrash) {
                deleteFilePermanently(file.id);
            } else {
                moveToTrash(file.id);
            }
        });
    }

    if (restoreBtn) {
        restoreBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            restoreFile(file.id);
        });
    }

    fileCard.addEventListener('click', function () {
        showFileDetails(file.id);
    });

    return fileCard;
}

async function showFileDetails(fileId) {
    try {
        const response = await fetch(`?api=true&id=${fileId}`);
        const file = await response.json();

        if (file.message) {
            alert(file.message);
            return;
        }

        fileDetailsContent.innerHTML = `
                    <div class="file-details">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <div style="font-size: 3rem;">${getFileIcon(file.name)}</div>
                            <h3>${file.name}</h3>
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <div>${file.category}</div>
                        </div>
                        <div class="form-group">
                            <label>Upload Date</label>
                            <div>${file.upload_date}</div>
                        </div>
                        <div class="form-group">
                            <label>File Size</label>
                            <div>${file.file_size}</div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <div>${file.description || 'No description provided'}</div>
                        </div>
                        ${file.is_deleted ?
                `<div class="form-group">
                                <label>Deleted Date</label>
                                <div>${file.deleted_date}</div>
                            </div>` : ''
            }
                        <div class="form-actions">
                            ${file.is_deleted ?
                `<button class="btn btn-success" id="restoreDetailBtn">Restore File</button>
                                 <button class="btn btn-danger" id="deletePermanentlyDetailBtn">Delete Permanently</button>` :
                `<button class="btn btn-danger" id="deleteDetailBtn">Move to Trash</button>`
            }
                            <button class="btn" id="closeDetailsBtn">Close</button>
                        </div>
                    </div>
                `;

        // Add event listeners to buttons in modal
        const restoreDetailBtn = document.getElementById('restoreDetailBtn');
        const deletePermanentlyDetailBtn = document.getElementById('deletePermanentlyDetailBtn');
        const deleteDetailBtn = document.getElementById('deleteDetailBtn');
        const closeDetailsBtn = document.getElementById('closeDetailsBtn');

        if (restoreDetailBtn) {
            restoreDetailBtn.addEventListener('click', function () {
                restoreFile(file.id);
                fileDetailsModal.style.display = 'none';
            });
        }

        if (deletePermanentlyDetailBtn) {
            deletePermanentlyDetailBtn.addEventListener('click', function () {
                deleteFilePermanently(file.id);
                fileDetailsModal.style.display = 'none';
            });
        }

        if (deleteDetailBtn) {
            deleteDetailBtn.addEventListener('click', function () {
                moveToTrash(file.id);
                fileDetailsModal.style.display = 'none';
            });
        }

        if (closeDetailsBtn) {
            closeDetailsBtn.addEventListener('click', function () {
                fileDetailsModal.style.display = 'none';
            });
        }

        fileDetailsModal.style.display = 'flex';
    } catch (error) {
        console.error('Error loading file details:', error);
        alert('Error loading file details. Please try again.');
    }
}

function getFileIcon(fileName) {
    const fileExtension = fileName.split('.').pop().toLowerCase();

    if (fileExtension === 'pdf') return '📕';
    if (['doc', 'docx'].includes(fileExtension)) return '📘';
    if (['xls', 'xlsx'].includes(fileExtension)) return '📗';
    if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExtension)) return '🖼️';

    return '📄';
}

async function handleFileUpload() {
    const fileInput = document.getElementById('fileInput');
    const fileName = document.getElementById('fileName').value;
    const fileCategory = document.getElementById('fileCategory').value;
    const fileDescription = document.getElementById('fileDescription').value;

    if (!fileInput.files[0]) {
        alert('Please select a file to upload.');
        return;
    }

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    formData.append('name', fileName);
    formData.append('category', fileCategory);
    formData.append('description', fileDescription);

    try {
        const response = await fetch('?api=true', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        alert(result.message);

        if (!result.message.includes('error')) {
            // Reset form and close modal
            uploadForm.reset();
            uploadModal.style.display = 'none';
            // Reload files
            loadActiveFiles();
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        alert('Error uploading file. Please try again.');
    }
}

async function moveToTrash(fileId) {
    if (confirm('Are you sure you want to move this file to trash?')) {
        try {
            const formData = new FormData();
            formData.append('action', 'trash');
            formData.append('id', fileId);

            const response = await fetch('?api=true', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            alert(result.message);
            loadActiveFiles();
        } catch (error) {
            console.error('Error moving file to trash:', error);
            alert('Error moving file to trash. Please try again.');
        }
    }
}

async function restoreFile(fileId) {
    try {
        const formData = new FormData();
        formData.append('action', 'restore');
        formData.append('id', fileId);

        const response = await fetch('?api=true', {
            method: 'POST',
            body: formData
        });

        const result = await response.json();
        alert(result.message);
        loadTrashFiles();

        // Switch to active files tab
        tabs[0].click();
    } catch (error) {
        console.error('Error restoring file:', error);
        alert('Error restoring file. Please try again.');
    }
}

async function deleteFilePermanently(fileId) {
    if (confirm('Are you sure you want to permanently delete this file? This action cannot be undone.')) {
        try {
            const response = await fetch('?api=true', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${fileId}`
            });

            const result = await response.json();
            alert(result.message);
            loadTrashFiles();
        } catch (error) {
            console.error('Error deleting file:', error);
            alert('Error deleting file. Please try again.');
        }
    }
}