 <nav class="nav-footer">
        <?php foreach($menuItems as $idx => $item): ?>
            <a href="<?php echo $item['link']; ?>" 
               class="nav-item 
               
               <?php 
               //echo $idx === 0 ? 'active' : '';
                ?>
               
               " 
               data-idx="<?php echo $idx; ?>">
                <i class="fa-solid <?php echo $item['icon']; ?>"></i>
                <span><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>

        <div class="nav-item nav-item-more" onclick="togglePopup()">
            <i class="fa-solid fa-ellipsis"></i>
            <span>More</span>
        </div>
    </nav>

    <div class="popup-menu" id="popupMenu">
        <?php foreach($menuItems as $item): ?>
            <a href="<?php echo $item['link']; ?>" class="popup-link">
                <i class="fa-solid <?php echo $item['icon']; ?>"></i> 
                <?php echo $item['label']; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <script src="../script.js"></script>
</body>
</html>