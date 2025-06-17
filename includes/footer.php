<?php
// includes/footer.php
?>
                </div> </div> <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Sistem Qurban RT 001 <?php echo date('Y'); ?></span>
                    </div>
                </div>
            </footer>
            </div>
        </div>
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Siap untuk Keluar?</h5>
                    <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">×</span>
                    </button>
                </div>
                <div class="modal-body">Pilih "Logout" di bawah jika Anda siap untuk mengakhiri sesi Anda saat ini.</div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Batal</button>
                    <a class="btn btn-primary" href="/sistem_qurban/public/auth.php?logout=true">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="/sistem_qurban/public/vendor/jquery/jquery.min.js"></script>
    <script src="/sistem_qurban/public/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script src="/sistem_qurban/public/vendor/jquery-easing/jquery.easing.min.js"></script>

    <script src="/sistem_qurban/public/js/sb-admin-2.min.js"></script>

    <script src="/sistem_qurban/public/vendor/datatables/jquery.dataTables.min.js"></script>
    <script src="/sistem_qurban/public/vendor/datatables/dataTables.bootstrap4.min.js"></script>

    <script src="/sistem_qurban/public/js/demo/datatables-demo.js"></script>

    </body>
</html>