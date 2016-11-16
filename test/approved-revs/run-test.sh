#!/bin/sh
#
# Run integrated ApprovedRevs test

source "/opt/meza/config/core/config.sh"
source "$m_scripts/shell-functions/base.sh"
rootCheck # function that does the checks for root/sudo



# create-wiki.sh for ApprovedRevs
imports_dir=new
wiki_id=arevs
wiki_name="Test Approved Revs Wiki"
slackwebhook=n
source "$m_scripts/create-wiki.sh"

ar_test="$m_test/approved-revs"
ar_pages="$ar_test/pages"
ar_images="$ar_test/images"
mw_maint="$m_mediawiki/maintenance"
wiki_id=arevs # do this again just to make sure

# Put ApprovedRevsSettings.php in arevs wiki
echo
echo "Copying: $ar_test/ApprovedRevsSettings.php"
echo "     to: $m_htdocs/wikis/$wiki_id/config"
cp "$ar_test/ApprovedRevsSettings.php" "$m_htdocs/wikis/$wiki_id/config"
echo -e "\n\nrequire_once __DIR__ . '/ApprovedRevsSettings.php';" >> "$m_htdocs/wikis/$wiki_id/config/postLocalSettings.php"


# Create users: Admin (Group:sysop), Editor (Group:Editors), Basic (no special group)
WIKI=arevs php "$mw_maint/createAndPromote.php" --bureaucrat --sysop --force Admin 1234
# WIKI=arevs php "$mw_maint/createAndPromote.php" --force Editor 1234 # MW1.25 doesn't support custom groups
WIKI=arevs php "$m_scripts/mezaCreateUser.php" --username=Editor --password=1234 --groups=Editors
WIKI=arevs php "$mw_maint/createAndPromote.php" --force Basic 1234


#
# Upload 5 versions of Test.png
#
tmp_images="/tmp/approved-revs-test-images"
mkdir "$tmp_images"
image_command="WIKI=arevs php '$mw_maint/importImages.php' '$tmp_images'"

cp "$ar_images/number-1.png" "$tmp_images/Test.png"
su -c "$image_command" apache

# sleep required so subsequent uploads don't cause old revs to have the same
# timestamp (no one should be uploading new revs every second). I don't think
# is actually required here, since at this point there are no old revs. Putting
# pause just in case.
sleep 2s
rm -f "$tmp_images/Test.png"
cp "$ar_images/number-2.png" "$tmp_images/Test.png"
su -c "$image_command" apache

sleep 2s
rm -f "$tmp_images/Test.png"
cp "$ar_images/number-3.png" "$tmp_images/Test.png"
su -c "$image_command" apache

sleep 2s
rm -f "$tmp_images/Test.png"
cp "$ar_images/number-4.png" "$tmp_images/Test.png"
su -c "$image_command" apache

sleep 2s
rm -f "$tmp_images/Test.png"
cp "$ar_images/number-5.png" "$tmp_images/Test.png"
su -c "$image_command" apache


#
# FIXME: HOW TO APPROVE?
#
# Approve revision 3




# Basics_page
filepath="$ar_pages/Basics_page"
pagename="Basic's page"
username="User:Basic"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"
(cat "$filepath" && echo -e "\nText added on first revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on second revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
username="User:Admin"
(cat "$filepath" && echo -e "\nText added on third revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"

# Do_not_approve
filepath="$ar_pages/Do_not_approve"
pagename="Do not approve"
username="User:Basic"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"
(cat "$filepath" && echo -e "\nText added on first revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
username="User:Editor"
(cat "$filepath" && echo -e "\nText added on second revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on third revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"

# Expert_info
filepath="$ar_pages/Expert_info"
pagename="Expert info"
username="User:Editor"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"
(cat "$filepath" && echo -e "\n[[Test prop::One]]" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
username="User:Basic"
(cat "$filepath" && echo -e "\n[[Test prop::Two]]" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
username="User:Editor"
(cat "$filepath" && echo -e "\n[[Test prop::Three]]" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\n[[Test prop::Four]]" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"

# File_Redirect_png
filepath="$ar_pages/File_Redirect_png"
pagename="File:Redirect.png"
username="User:Admin"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"

# File_redirects
filepath="$ar_pages/File_redirects"
pagename="File redirects"
username="User:Admin"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"

# Initially_unapproved_page
filepath="$ar_pages/Initially_unapproved_page"
pagename="Initially unapproved page"
username="User:Basic"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"
(cat "$filepath" && echo -e "\nText added on first revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on second revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on third revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"

# Main_Page
filepath="$ar_pages/Main_Page"
pagename="Main Page"
username="User:Admin"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"

# Not_just_Basics
filepath="$ar_pages/Not_just_Basics"
pagename="Not just Basic's"
username="User:Basic"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"
(cat "$filepath" && echo -e "\nText added on first revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
username="User:Editor"
(cat "$filepath" && echo -e "\nText added on second revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
username="User:Basic"
(cat "$filepath" && echo -e "\nText added on third revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"

# Property_Test_prop
filepath="$ar_pages/Property_Test_prop"
pagename="Property:Test prop"
username="User:Admin"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"

# User_Basic
filepath="$ar_pages/User_Basic"
pagename="User:Basic"
username="User:Basic"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"
(cat "$filepath" && echo -e "\nText added on first revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on second revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on third revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"

# With_override
filepath="$ar_pages/With_override"
pagename="With override"
username="User:Admin"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"
(cat "$filepath" && echo -e "\nText added on first revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on second revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on third revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"

# Without_override
filepath="$ar_pages/Without_override"
pagename="Without override"
username="User:Basic"
WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename" < "$filepath"
(cat "$filepath" && echo -e "\nText added on first revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on second revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"
(cat "$filepath" && echo -e "\nText added on third revision" ) | WIKI=arevs php "$mw_maint/edit.php" -u "$username" -a "$pagename"


echo
echo "Test setup complete"
