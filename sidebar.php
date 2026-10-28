<?php

$currentPage = basename($_SERVER["PHP_SELF"]);

?>

<div class="sidebar" style="height:100vh; overflow-y:auto;">

    <div class="logo">

        <div class="logo-box">
            U
        </div>

        <h2>UniStay</h2>

    </div>


    <div class="menu">

        <a href="dashboard.php"
           class="<?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-house"></i>
            Dashboard

        </a>


        <a href="complaints.php"
           class="<?php echo ($currentPage == 'complaints.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-screwdriver-wrench"></i>
            Complaints

        </a>


        <a href="leave_requests.php"
           class="<?php echo ($currentPage == 'leave_requests.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-calendar-check"></i>
            Leave Requests

        </a>


        <!-- ROOM CHANGE - WARDEN SIDE -->
        <a href="room_change_requests.php"
           class="<?php echo ($currentPage == 'room_change_requests.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-arrow-right-arrow-left"></i>
            Room Changes

        </a>


        <a href="mess.php"
           class="<?php echo ($currentPage == 'mess.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-utensils"></i>
            Mess

        </a>


        <a href="laundry.php"
           class="<?php echo ($currentPage == 'laundry.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-shirt"></i>
            Laundry

        </a>


        <a href="fees.php"
           class="<?php echo ($currentPage == 'fees.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-indian-rupee-sign"></i>
            Fees

        </a>


        <a href="lost_found.php"
           class="<?php echo ($currentPage == 'lost_found.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-magnifying-glass-location"></i>
            Lost & Found

        </a>


        <a href="events.php"
           class="<?php echo ($currentPage == 'events.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-calendar-days"></i>
            Events

        </a>


        <a href="visitor_parcel.php"
           class="<?php echo ($currentPage == 'visitor_parcel.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-door-open"></i>
            Visitors & Parcels

        </a>


        <a href="emergency.php"
           class="<?php echo ($currentPage == 'emergency.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-triangle-exclamation"></i>
            Emergency SOS

        </a>


        <a href="notices.php"
           class="<?php echo ($currentPage == 'notices.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-bullhorn"></i>
            Notices

        </a>


        <a href="students.php"
           class="<?php echo ($currentPage == 'students.php') ? 'active' : ''; ?>">

            <i class="fa-solid fa-user-graduate"></i>
            Students

        </a>


        <a href="../logout.php">

            <i class="fa-solid fa-right-from-bracket"></i>
            Logout

        </a>

    </div>

</div>