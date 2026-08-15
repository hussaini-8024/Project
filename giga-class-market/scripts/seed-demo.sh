#!/usr/bin/env bash
# Seed demo content for Giga Class Market local/dev WordPress.
set -euo pipefail

WP_PATH="${WP_PATH:-/var/www/gigaclass}"
cd "$WP_PATH"

wp plugin activate giga-class-market --allow-root
wp theme activate giga-class-market --allow-root
wp rewrite structure '/%postname%/' --allow-root
wp rewrite flush --allow-root

# Ensure pages/templates from activator
wp eval 'GCM_Activator::activate();' --allow-root

# Company settings
wp eval '
$settings = GCM_Settings_Service::get_settings();
$settings["company"]["name"] = "Giga Class Market";
$settings["company"]["email"] = "support@gigaclass.local";
$settings["company"]["phone"] = "+92 300 0000000";
$settings["company"]["whatsapp"] = "923000000000";
$settings["company"]["address"] = "Islamabad, Pakistan";
$settings["payment"]["methods"]["Bank"]["account_name"] = "Giga Class Market";
$settings["payment"]["methods"]["Bank"]["account_no"] = "PK00BANK0000001";
$settings["payment"]["methods"]["JazzCash"]["account_no"] = "0300-0000000";
$settings["payment"]["methods"]["Easypaisa"]["account_no"] = "0300-1111111";
GCM_Settings_Service::update_settings($settings);
echo "settings ok\n";
' --allow-root

# Slides
for i in 1 2 3; do
  EXISTS=$(wp post list --post_type=gcm_slide --name="hero-slide-$i" --field=ID --allow-root || true)
  if [ -z "$EXISTS" ]; then
    wp post create --post_type=gcm_slide --post_status=publish --post_title="Hero Slide $i" --post_name="hero-slide-$i" --post_content="Premium learning for ambitious professionals." --allow-root >/dev/null
  fi
done

# Testimonials
wp eval '
$samples = array(
  array("Ayesha Khan", "The networking track helped me land a stronger role within months."),
  array("Omar Farooq", "Clean lessons, practical labs, and a professional learning experience."),
  array("Sara Ali", "Payment verification was smooth and the dashboard feels premium."),
);
foreach ($samples as $index => $sample) {
  $id = wp_insert_post(array(
    "post_type" => "gcm_testimonial",
    "post_status" => "publish",
    "post_title" => $sample[0],
    "post_content" => $sample[1],
  ));
  if ($id && !is_wp_error($id)) {
    update_post_meta($id, "_gcm_rating", 5);
    update_post_meta($id, "_gcm_role", "GCM Learner");
  }
}
echo "testimonials ok\n";
' --allow-root

# Courses + curriculum
wp eval '
$courses = array(
  array(
    "title" => "Networking Fundamentals",
    "excerpt" => "Master TCP/IP, switching, routing, and real network troubleshooting.",
    "category" => "networking",
    "price" => 14999,
    "duration" => "12 hours",
    "instructor" => "Engr. Bilal Ahmed",
    "featured" => true,
    "learn" => "OSI & TCP/IP models\nIP addressing & subnetting\nSwitching and VLANs\nRouting basics",
    "requirements" => "Basic computer literacy\nWillingness to practice labs",
    "modules" => array(
      array("Introduction", array("Welcome to Networking", "How Networks Work")),
      array("Core Skills", array("IP Addressing", "Subnetting Labs", "Switching Essentials")),
    ),
  ),
  array(
    "title" => "Cyber Security Essentials",
    "excerpt" => "Defend systems with practical security foundations and threat awareness.",
    "category" => "cyber-security",
    "price" => 17999,
    "duration" => "14 hours",
    "instructor" => "Nadia Rehman",
    "featured" => true,
    "learn" => "Security principles\nThreat modeling\nHardening basics\nIncident response intro",
    "requirements" => "Networking basics recommended",
    "modules" => array(
      array("Foundations", array("Security Mindset", "CIA Triad")),
      array("Defense", array("Access Control", "Network Defense")),
    ),
  ),
  array(
    "title" => "Full-Stack Web Development",
    "excerpt" => "Build modern web applications with frontend and backend fundamentals.",
    "category" => "web-development",
    "price" => 19999,
    "duration" => "20 hours",
    "instructor" => "Hassan Malik",
    "featured" => true,
    "learn" => "HTML/CSS/JS foundations\nResponsive UI\nAPIs and databases\nDeploy a project",
    "requirements" => "Laptop and internet connection",
    "modules" => array(
      array("Frontend", array("HTML & Semantics", "CSS Layouts", "JavaScript Basics")),
      array("Backend", array("APIs", "Databases", "Deployment")),
    ),
  ),
  array(
    "title" => "Cloud & DevOps Primer",
    "excerpt" => "Understand cloud models, CI/CD ideas, and modern operations workflows.",
    "category" => "cloud",
    "price" => 15999,
    "duration" => "10 hours",
    "instructor" => "Fatima Noor",
    "featured" => false,
    "learn" => "Cloud models\nContainers intro\nCI/CD concepts",
    "requirements" => "Basic Linux familiarity helpful",
    "modules" => array(
      array("Cloud Basics", array("IaaS PaaS SaaS", "Regions and Reliability")),
      array("Ops", array("Pipelines Overview", "Monitoring Basics")),
    ),
  ),
);

foreach ($courses as $data) {
  $existing = get_page_by_title($data["title"], OBJECT, "gcm_course");
  if ($existing) {
    $course_id = (int) $existing->ID;
  } else {
    $course_id = GCM_Course_Service::create(array(
      "title" => $data["title"],
      "excerpt" => $data["excerpt"],
      "content" => "<p>" . esc_html($data["excerpt"]) . "</p><p>This premium Giga Class Market course blends theory with hands-on practice.</p>",
      "status" => "publish",
      "price" => $data["price"],
      "duration" => $data["duration"],
      "instructor" => $data["instructor"],
      "what_you_learn" => $data["learn"],
      "requirements" => $data["requirements"],
      "rating" => 4.9,
      "featured" => $data["featured"],
    ));
  }
  if (is_wp_error($course_id) || !$course_id) {
    continue;
  }
  wp_set_object_terms($course_id, $data["category"], "gcm_category", false);
  GCM_Post_Types::set_featured($course_id, !empty($data["featured"]));

  $existing_modules = GCM_Curriculum_Service::get_course_curriculum($course_id);
  if (empty($existing_modules)) {
    $payload = array();
    foreach ($data["modules"] as $sort => $module) {
      $lessons = array();
      foreach ($module[1] as $lsort => $lesson_title) {
        $lessons[] = array(
          "title" => $lesson_title,
          "content" => "Lesson content for " . $lesson_title,
          "video_url" => "https://www.youtube.com/watch?v=dQw4w9WgXcQ",
          "duration_minutes" => 12,
          "sort_order" => $lsort,
        );
      }
      $payload[] = array(
        "title" => $module[0],
        "sort_order" => $sort,
        "lessons" => $lessons,
      );
    }
    GCM_Curriculum_Service::save_course_curriculum($course_id, $payload);
  }
}
echo "courses ok\n";
' --allow-root

# Menus
MENU_ID=$(wp menu list --fields=term_id,name --format=csv --allow-root | awk -F, '$2=="Primary"{print $1; exit}')
if [ -z "${MENU_ID:-}" ]; then
  MENU_ID=$(wp menu create "Primary" --porcelain --allow-root)
fi
wp menu item list "$MENU_ID" --format=count --allow-root | grep -q '[1-9]' || {
  wp menu item add-custom "$MENU_ID" "Home" "$(wp option get home --allow-root)/" --allow-root
  wp menu item add-custom "$MENU_ID" "About" "$(wp option get home --allow-root)/about/" --allow-root
  wp menu item add-custom "$MENU_ID" "Courses" "$(wp option get home --allow-root)/courses/" --allow-root
  wp menu item add-custom "$MENU_ID" "Contact" "$(wp option get home --allow-root)/contact/" --allow-root
}
wp menu location assign "$MENU_ID" primary --allow-root || true

echo "Seed complete."
