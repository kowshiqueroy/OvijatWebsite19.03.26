
</main><!-- /.main-content -->
</div><!-- /.app-wrap -->

<!-- ── Global quick-add call modal ──────────────────────────────────────────── -->
<div class="modal fade" id="quickCallModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content cc-modal">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-phone-plus me-2"></i>Log Manual Call Entry</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="quickCallForm">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="src" id="qcPhone" class="form-control" placeholder="01XXXXXXXXX"
                                   oninput="contactAutofill(this.value)" required>
                            <div id="qcContactHint" class="contact-hint mt-1"></div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Direction <span class="text-danger">*</span></label>
                            <select name="call_direction" class="form-select" required>
                                <option value="inbound">Inbound</option>
                                <option value="outbound">Outbound</option>
                                <option value="internal">Internal</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Destination</label>
                            <input type="text" name="dst" class="form-control" placeholder="Extension or number">
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">Status</label>
                            <select name="disposition" class="form-select">
                                <option value="ANSWERED">Answered</option>
                                <option value="NO ANSWER">No Answer</option>
                                <option value="BUSY">Busy</option>
                                <option value="FAILED">Failed</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label">Duration (sec)</label>
                            <input type="number" name="duration" class="form-control" placeholder="0" min="0">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Call Date &amp; Time</label>
                            <input type="datetime-local" name="calldate" class="form-control"
                                   value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Mark</label>
                            <select name="call_mark" class="form-select">
                                <option value="normal">Normal</option>
                                <option value="follow_up">Follow Up</option>
                                <option value="callback">Callback</option>
                                <option value="urgent">Urgent</option>
                                <option value="escalated">Escalated</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="manual_notes" class="form-control" rows="3"
                                      placeholder="What was discussed? Any action needed?"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitQuickCall()">
                    <i class="fas fa-save me-1"></i> Save Call
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Floating action button (mobile) ──────────────────────────────────────── -->
<button class="fab d-lg-none" data-bs-toggle="modal" data-bs-target="#quickCallModal"
        title="Log a call">
    <i class="fas fa-phone-plus"></i>
</button>

<!-- ── Toast container ──────────────────────────────────────────────────────── -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>
