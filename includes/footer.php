<style>
    body {
        padding-bottom: 70px;
        /* ป้องกัน footer บังเนื้อหา */
        box-sizing: border-box;
    }

    .site-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        box-shadow: 0 -2px 6px rgba(0, 0, 0, 0.06);
        padding: 10px 0;
        /* padding บน–ล่างเท่านั้น */
        z-index: 900;
    }

    /* ใช้ container เหมือน navbar → ไม่ชิดขอบทุกจอ */
    .footer-container {
        width: 100%;
        padding: 0 clamp(20px, 4vw, 80px);
        /* <<< ยืดตามขนาดจอ */
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-sizing: border-box;
    }

    .footer-left {
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: "Sarabun", sans-serif;
        font-size: 0.85rem;
        color: #475569;
    }

    .footer-dot {
        width: 6px;
        height: 6px;
        display: inline-block;
        border-radius: 999px;
        background: #22c55e;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.25);
    }

    .footer-right {
        font-family: "Sarabun", sans-serif;
        font-size: 0.85rem;
        color: #64748b;
        text-align: right;
        white-space: nowrap;
    }

    @media (max-width: 640px) {
        .footer-container {
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            padding: 0 20px;
        }

        .footer-right {
            text-align: left;
            white-space: normal;
        }
    }
</style>

<footer class="site-footer">
    <div class="footer-container">

        <div class="footer-left">
            <span class="footer-dot"></span>
            <span>© <?= date('Y'); ?> Nurse Activity Recording System</span>
        </div>

        <div class="footer-right">
            กลุ่มงานสารสนเทศ<br>
            โรงพยาบาลเทพา
        </div>

    </div>
</footer>

<!-- jQuery (ถ้ามีแล้วไม่ต้องใส่ซ้ำ) -->
<!-- jQuery & Select2 are already in header.php -->

</body>

</html>