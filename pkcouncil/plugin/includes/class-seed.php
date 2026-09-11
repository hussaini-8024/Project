<?php
if (!defined('ABSPATH')) {
    exit;
}

class PKC_Seed {
    public static function run() {
        if (get_option('pkc_seeded')) {
            return;
        }
        global $wpdb;
        $now = PKC_DB::now();

        $cats = array(
            array('Biology', 'biology', 'Life sciences and FSc / MDCAT biology.'),
            array('Chemistry', 'chemistry', 'Physical, organic, and inorganic chemistry.'),
            array('Physics', 'physics', 'Mechanics, waves, electricity, and modern physics.'),
            array('Mathematics', 'mathematics', 'Algebra, calculus, and exam mathematics.'),
            array('English', 'english', 'Comprehension, grammar, and academic writing.'),
            array('Entry Tests', 'entry-tests', 'MDCAT and university entry preparation.'),
        );
        $cat_ids = array();
        $i = 0;
        foreach ($cats as $c) {
            $wpdb->insert(PKC_DB::categories(), array(
                'name' => $c[0], 'slug' => $c[1], 'description' => $c[2],
                'parent_id' => 0, 'sort_order' => $i++, 'created_at' => $now,
            ));
            $cat_ids[$c[1]] = (int) $wpdb->insert_id;
        }

        $methods = array(
            array('EasyPaisa', 'easypaisa', 'Send the course amount to the EasyPaisa account, then enter the transaction ID and upload the screenshot.', "Account title: PKCouncil\nAccount number: 0300-1234567"),
            array('JazzCash', 'jazzcash', 'Transfer via JazzCash, then submit your transaction ID and receipt.', "Account title: PKCouncil\nAccount number: 0301-7654321"),
            array('Bank Transfer', 'bank-transfer', 'Transfer to the PKCouncil bank account and upload the transfer slip.', "Bank: HBL\nTitle: PKCouncil\nIBAN: PK00HABB0000000000000000"),
        );
        foreach ($methods as $n => $m) {
            $wpdb->insert(PKC_DB::payment_methods(), array(
                'name' => $m[0], 'slug' => $m[1], 'instructions' => $m[2],
                'account_details' => $m[3], 'is_active' => 1, 'sort_order' => $n,
            ));
        }

        $tpass = PKC_Auth::hash_password('Teacher@PKCouncil');
        $teachers = array(
            array('dr.farah', 'farah@pkcouncil.org', 'Dr. Farah Malik', 'Lead Biology instructor. Former FSc and MDCAT faculty with a focus on conceptual clarity and exam technique.'),
            array('prof.ahmed', 'ahmed@pkcouncil.org', 'Prof. Ahmed Khan', 'Chemistry instructor specialising in physical chemistry, stoichiometry, and organic reaction maps.'),
            array('sana.iqbal', 'sana@pkcouncil.org', 'Ms. Sana Iqbal', 'Physics and quantitative reasoning instructor. Builds problem-solving fluency from first principles.'),
        );
        $tid = array();
        foreach ($teachers as $t) {
            $wpdb->insert(PKC_DB::teachers(), array(
                'username' => $t[0], 'email' => $t[1], 'password_hash' => $tpass,
                'full_name' => $t[2], 'whatsapp' => '923001111111', 'profile_bio' => $t[3],
                'status' => 'active', 'must_change_password' => 1, 'created_at' => $now, 'updated_at' => $now,
            ));
            $tid[] = (int) $wpdb->insert_id;
        }

        $courses_src = array(
            array('FSc Biology', 'fsc-biology', $cat_ids['biology'], 12000, $tid[0], 'A complete self-paced Biology course for FSc students — notes, diagrams, MCQs, and teacher support.', array('Cell biology with exam-ready diagrams', 'Plant and animal physiology mapped to the board syllabus', 'Genetics and evolution with practice sets', 'MDCAT-style MCQs after every module'), 'intermediate'),
            array('FSc Chemistry', 'fsc-chemistry', $cat_ids['chemistry'], 12000, $tid[1], 'Physical, organic, and inorganic chemistry with structured notes and a growing MCQ bank.', array('Stoichiometry and mole concept mastery', 'Organic reaction pathways without rote panic', 'Periodic trends explained visually', 'Chapter-end MCQs with explanations'), 'intermediate'),
            array('FSc Physics', 'fsc-physics', $cat_ids['physics'], 12000, $tid[2], 'Mechanics to modern physics with worked examples, formula sheets, and timed quizzes.', array('Mechanics built from first principles', 'Waves, optics, and electricity with problem sets', 'Formula sheets you can actually use', 'Timed numerical practice'), 'intermediate'),
            array('MDCAT Prep', 'mdcat-prep', $cat_ids['entry-tests'], 18000, $tid[0], 'A high-yield MDCAT pathway: Biology-heavy practice, chemistry/physics support, and exam-length papers.', array('High-yield Biology for MDCAT', 'Mixed-subject timed papers', 'Intelligent question repetition', 'Teacher chat for stubborn topics'), 'advanced'),
            array('Intermediate Mathematics', 'intermediate-mathematics', $cat_ids['mathematics'], 10000, $tid[2], 'Algebra, functions, and calculus foundations for intermediate students who want precision.', array('Algebraic fluency drills', 'Functions and graphs', 'Introductory calculus', 'Past-paper style quizzes'), 'beginner'),
            array('Academic English', 'academic-english', $cat_ids['english'], 8000, $tid[1], 'Comprehension, grammar, and writing for board exams and university entry tests.', array('Close reading of exam passages', 'High-frequency grammar traps', 'Essay and summary structure', 'Practice MCQs for English papers'), 'beginner'),
        );

        $course_ids = array();
        foreach ($courses_src as $idx => $src) {
            $faqs = wp_json_encode(array(
                array('q' => 'Is this self-paced?', 'a' => 'Yes. There are no live classes. You study modules, notes, and quizzes on your schedule.'),
                array('q' => 'Will I get a teacher?', 'a' => 'Yes. After payment approval you can chat with the instructor assigned to this course.'),
                array('q' => 'Are materials downloadable?', 'a' => 'Authorised students can access protected PDFs through the student portal. Direct public links are blocked.'),
            ));
            $includes = wp_json_encode(array('Structured modules', 'Protected PDF notes', 'Practice MCQs', 'Timed quizzes', 'Teacher chat', 'Progress tracking'));
            $wpdb->insert(PKC_DB::courses(), array(
                'title' => $src[0], 'slug' => $src[1], 'excerpt' => $src[5],
                'description' => $src[5] . ' PKCouncil courses combine curated notes, a living MCQ bank, and course-specific teacher support.',
                'overview' => 'This course is organised into modules. Complete the notes, attempt the MCQs, sit the quiz, and message your teacher when a concept refuses to settle.',
                'category_id' => $src[2], 'price' => $src[3], 'level' => $src[6],
                'course_type' => 'self-paced', 'is_featured' => $idx < 4 ? 1 : 0, 'is_top' => $idx < 3 ? 1 : 0,
                'visibility' => 'public', 'what_you_learn' => wp_json_encode($src[4]), 'includes' => $includes, 'faqs' => $faqs,
                'status' => 'published', 'enrolled_count' => 0, 'created_at' => $now, 'updated_at' => $now,
            ));
            $cid = (int) $wpdb->insert_id;
            $course_ids[] = $cid;
            PKC_Access::assign_teacher($src[4], $cid);

            $mod_titles = array('Foundations', 'Core syllabus', 'Practice & assessment');
            foreach ($mod_titles as $mi => $mt) {
                $wpdb->insert(PKC_DB::modules(), array(
                    'course_id' => $cid, 'title' => $mt,
                    'description' => 'Module ' . ($mi + 1) . ' of ' . $src[0],
                    'sort_order' => $mi,
                ));
                $mid = (int) $wpdb->insert_id;
                for ($li = 1; $li <= 3; $li++) {
                    $wpdb->insert(PKC_DB::lessons(), array(
                        'module_id' => $mid, 'course_id' => $cid,
                        'title' => $mt . ' — Lesson ' . $li,
                        'content' => '<p>Study this lesson carefully. Highlight definitions, redraw diagrams from memory, then attempt the related MCQs.</p><p>When you finish, mark the lesson complete so your PKCouncil progress stays honest.</p>',
                        'lesson_type' => $li === 3 ? 'notes' : 'text',
                        'sort_order' => $li, 'duration_minutes' => 25,
                    ));
                    $lid = (int) $wpdb->insert_id;
                    if ($li === 3) {
                        $pdf = PKC_Materials::write_sample_pdf(
                            'notes-' . $cid . '-' . $lid . '.pdf',
                            $src[0] . ' — ' . $mt,
                            'PKCouncil protected notes. For enrolled students only.'
                        );
                        $wpdb->insert(PKC_DB::materials(), array(
                            'course_id' => $cid, 'lesson_id' => $lid,
                            'title' => $src[0] . ' notes — ' . $mt . '.pdf',
                            'file_path' => $pdf, 'file_type' => 'pdf', 'file_size' => 4096,
                            'is_downloadable' => 1, 'created_at' => $now,
                        ));
                    }
                }
            }

            $wpdb->insert(PKC_DB::quizzes(), array(
                'course_id' => $cid,
                'title' => $src[0] . ' Progress Quiz',
                'description' => 'A timed paper drawn from the PKCouncil question bank for this course.',
                'question_count' => 10,
                'time_limit_minutes' => 15,
                'passing_percent' => 50,
                'max_attempts' => 3,
                'randomize_questions' => 1,
                'randomize_options' => 1,
                'show_explanations' => 'after_submit',
                'exclude_attempted' => 'deprioritize',
                'exclude_days' => 14,
                'allow_repeat_when_exhausted' => 1,
                'result_visibility' => 'immediate',
                'status' => 'published',
                'created_at' => $now,
            ));
        }

        self::questions($course_ids);

        $spass = PKC_Auth::hash_password('Student@PKCouncil');
        $wpdb->insert(PKC_DB::students(), array(
            'username' => 'aisha.khan', 'email' => 'aisha@student.pkcouncil.org', 'password_hash' => $spass,
            'full_name' => 'Aisha Khan', 'whatsapp' => '923002222222', 'address' => 'Lahore',
            'status' => 'active', 'must_change_password' => 1, 'created_at' => $now, 'updated_at' => $now,
        ));
        $sid = (int) $wpdb->insert_id;
        PKC_Access::grant_course($sid, $course_ids[0]);
        PKC_Access::grant_course($sid, $course_ids[3]);

        $wpdb->insert(PKC_DB::students(), array(
            'username' => 'ali.raza', 'email' => 'ali@student.pkcouncil.org', 'password_hash' => $spass,
            'full_name' => 'Ali Raza', 'whatsapp' => '923003333333', 'address' => 'Karachi',
            'status' => 'active', 'must_change_password' => 1, 'created_at' => $now, 'updated_at' => $now,
        ));

        $wpdb->insert(PKC_DB::coupons(), array(
            'code' => 'WELCOME10', 'discount_type' => 'percent', 'discount_value' => 10,
            'max_uses' => 100, 'max_per_user' => 1, 'used_count' => 0, 'min_amount' => 0,
            'is_active' => 1, 'created_at' => $now,
        ));
        $welcome = (int) $wpdb->insert_id;
        $wpdb->insert(PKC_DB::coupons(), array(
            'code' => 'MDCAT20', 'discount_type' => 'percent', 'discount_value' => 20,
            'max_uses' => 50, 'max_per_user' => 1, 'used_count' => 0, 'min_amount' => 0,
            'is_active' => 1, 'created_at' => $now,
        ));
        $mdcat_c = (int) $wpdb->insert_id;
        $wpdb->insert(PKC_DB::coupon_courses(), array('coupon_id' => $mdcat_c, 'course_id' => $course_ids[3]));

        $reviews = array(
            array($course_ids[0], 'Aisha Khan', 5, 'The Biology modules feel like a proper academic council — notes, MCQs, and a teacher who actually owns the subject.', 1, 1),
            array($course_ids[3], 'Hassan Ali', 5, 'MDCAT prep without the noise of live classes. I study at night, sit timed papers, and message Dr. Farah when I am stuck.', 1, 1),
            array($course_ids[1], 'Zara Sheikh', 4, 'Chemistry finally has a sequence. Organic used to be chaos; the reaction maps are excellent.', 1, 1),
            array($course_ids[2], 'Usman Tariq', 5, 'Physics numericals with a timer changed how I practise. The portal is calm and serious — which is what I wanted.', 0, 1),
        );
        foreach ($reviews as $r) {
            $wpdb->insert(PKC_DB::reviews(), array(
                'course_id' => $r[0], 'student_id' => 0, 'author_name' => $r[1], 'rating' => $r[2],
                'content' => $r[3], 'status' => 'approved', 'is_featured' => $r[4], 'show_on_homepage' => $r[5],
                'created_at' => $now,
            ));
        }

        PKC_Notifications::add('student', $sid, 'Welcome to PKCouncil', 'Your Biology and MDCAT courses are ready. Change your password from Profile after you look around.', 'account', pkc_url('student/'));

        update_option('pkc_seeded', 1);
        PKC_Audit::log('demo_seeded', 'system', 0);
    }

    protected static function questions($course_ids) {
        $bank = array(
            array($course_ids[0], 'Cell Biology', 'The basic structural and functional unit of life is the:', array('Tissue', 'Cell', 'Organ', 'Organism'), 1, 'Cells are the fundamental units of living organisms.'),
            array($course_ids[0], 'Cell Biology', 'Which organelle is the primary site of aerobic respiration?', array('Ribosome', 'Chloroplast', 'Mitochondrion', 'Golgi apparatus'), 2, 'Mitochondria generate ATP through cellular respiration.'),
            array($course_ids[0], 'Genetics', 'A segment of DNA that codes for a polypeptide is a:', array('Chromosome', 'Gene', 'Genome', 'Nucleotide'), 1, 'A gene is a DNA sequence that encodes a functional product.'),
            array($course_ids[0], 'Physiology', 'Haemoglobin is found in:', array('Plasma', 'White blood cells', 'Red blood cells', 'Platelets'), 2, 'RBCs carry haemoglobin for oxygen transport.'),
            array($course_ids[0], 'Botany', 'The process by which plants lose water vapour is:', array('Transpiration', 'Respiration', 'Osmosis', 'Guttation'), 0, 'Transpiration is water vapour loss, mainly through stomata.'),
            array($course_ids[0], 'Ecology', 'An organism that produces its own food is a:', array('Consumer', 'Decomposer', 'Producer', 'Parasite'), 2, 'Producers synthesise organic compounds, typically via photosynthesis.'),
            array($course_ids[0], 'Genetics', 'In a heterozygous genotype, the alleles are:', array('Identical', 'Different', 'Absent', 'Recessive only'), 1, 'Heterozygous means two different alleles of a gene.'),
            array($course_ids[0], 'Human', 'The functional unit of the kidney is the:', array('Neuron', 'Nephron', 'Alveolus', 'Villus'), 1, 'Each kidney contains nephrons that filter blood.'),
            array($course_ids[0], 'Cell Biology', 'Ribosomes are directly involved in:', array('Photosynthesis', 'Protein synthesis', 'Lipid storage', 'DNA replication'), 1, 'Ribosomes translate mRNA into polypeptides.'),
            array($course_ids[0], 'Evolution', 'Natural selection was proposed as a mechanism of evolution by:', array('Lamarck', 'Mendel', 'Darwin', 'Pasteur'), 2, 'Darwin proposed natural selection as the mechanism of evolution.'),
            array($course_ids[1], 'Stoichiometry', 'The number of particles in one mole of a substance is:', array('6.022 × 10²³', '3.00 × 10⁸', '1.60 × 10⁻¹⁹', '9.81'), 0, 'Avogadro’s number is 6.022 × 10²³ mol⁻¹.'),
            array($course_ids[1], 'Atomic', 'The atomic number of an element is the number of:', array('Neutrons', 'Protons', 'Nucleons', 'Electrons in M shell'), 1, 'Atomic number equals the proton count.'),
            array($course_ids[1], 'Organic', 'The general formula of alkanes is:', array('CnH2n', 'CnH2n+2', 'CnH2n-2', 'CnHn'), 1, 'Alkanes are saturated hydrocarbons, CnH2n+2.'),
            array($course_ids[1], 'Equilibrium', 'A catalyst increases the rate of a reaction by:', array('Increasing temperature', 'Increasing yield', 'Lowering activation energy', 'Changing Kc'), 2, 'Catalysts provide a lower-energy pathway.'),
            array($course_ids[1], 'Acids', 'pH is defined as:', array('-log[OH⁻]', '-log[H⁺]', 'log[H⁺]', 'log Ka'), 1, 'pH = −log₁₀[H⁺].'),
            array($course_ids[1], 'Bonding', 'An ionic bond is formed by:', array('Sharing of electrons', 'Transfer of electrons', 'Hydrogen bridging only', 'Metallic delocalisation only'), 1, 'Ionic bonds arise from electron transfer between atoms.'),
            array($course_ids[1], 'Organic', 'Ethene is an example of an:', array('Alkane', 'Alkene', 'Alkyne', 'Alcohol'), 1, 'Ethene (C2H4) contains a carbon–carbon double bond.'),
            array($course_ids[1], 'Gases', 'At constant temperature, Boyle’s law relates:', array('P and T', 'V and T', 'P and V', 'n and T'), 2, 'Boyle: P ∝ 1/V at constant T and n.'),
            array($course_ids[1], 'Periodic', 'Electronegativity across a period generally:', array('Decreases', 'Increases', 'Remains zero', 'Oscillates randomly'), 1, 'Electronegativity typically increases left to right.'),
            array($course_ids[1], 'Solutions', 'A solution with pH 3 is:', array('Neutral', 'Strongly basic', 'Acidic', 'A buffer always'), 2, 'pH < 7 indicates an acidic solution.'),
            array($course_ids[2], 'Mechanics', 'The SI unit of force is the:', array('Joule', 'Watt', 'Newton', 'Pascal'), 2, '1 N = 1 kg·m/s².'),
            array($course_ids[2], 'Mechanics', 'Acceleration is the rate of change of:', array('Distance', 'Displacement', 'Velocity', 'Speed only'), 2, 'a = Δv / Δt.'),
            array($course_ids[2], 'Waves', 'The relationship v = fλ connects speed with:', array('Force and mass', 'Frequency and wavelength', 'Charge and field', 'Power and time'), 1, 'Wave speed equals frequency times wavelength.'),
            array($course_ids[2], 'Electricity', 'Ohm’s law is commonly written as:', array('V = IR', 'P = mv', 'F = kx', 'E = mc²'), 0, 'Potential difference equals current times resistance.'),
            array($course_ids[2], 'Energy', 'The SI unit of energy is the:', array('Newton', 'Joule', 'Ampere', 'Tesla'), 1, 'Energy is measured in joules.'),
            array($course_ids[2], 'Optics', 'A concave lens is usually:', array('Converging', 'Diverging', 'A prism', 'Always magnifying'), 1, 'Concave lenses diverge parallel rays.'),
            array($course_ids[2], 'Modern', 'The photoelectric effect provides evidence for:', array('Wave nature of sound', 'Particle nature of light', 'Continuous spectra only', 'Nuclear fission'), 1, 'Photoelectric effect supports photons — particle-like light.'),
            array($course_ids[2], 'Mechanics', 'Momentum is defined as:', array('mv', '1/2 mv²', 'mgh', 'F/A'), 0, 'Linear momentum p = mv.'),
            array($course_ids[2], 'Heat', 'Absolute zero is:', array('0 °C', '100 °C', '0 K', '273 °C'), 2, '0 K is absolute zero.'),
            array($course_ids[2], 'Waves', 'Sound waves in air are:', array('Transverse', 'Longitudinal', 'Electromagnetic', 'Stationary only'), 1, 'Sound in air is a longitudinal mechanical wave.'),
            array($course_ids[3], 'MDCAT', 'Which vitamin deficiency causes scurvy?', array('Vitamin A', 'Vitamin B12', 'Vitamin C', 'Vitamin D'), 2, 'Scurvy is caused by lack of ascorbic acid (vitamin C).'),
            array($course_ids[3], 'MDCAT', 'The pacemaker of the heart is the:', array('AV node', 'SA node', 'Purkinje fibres', 'Bundle of His'), 1, 'The sinoatrial node initiates the heartbeat.'),
            array($course_ids[3], 'MDCAT', 'Insulin is secreted by the:', array('Adrenal cortex', 'Thyroid', 'β-cells of pancreas', 'Pituitary'), 2, 'Pancreatic β-cells secrete insulin.'),
            array($course_ids[3], 'MDCAT', 'DNA pairing is:', array('A-G and C-T', 'A-T and G-C', 'A-C and G-T', 'A-U only in DNA'), 1, 'DNA bases pair A–T and G–C.'),
            array($course_ids[3], 'MDCAT', 'The largest part of the human brain is the:', array('Cerebellum', 'Medulla', 'Cerebrum', 'Thalamus'), 2, 'The cerebrum is the largest brain region.'),
            array($course_ids[3], 'MDCAT', 'Enzymes are primarily:', array('Lipids', 'Carbohydrates', 'Proteins', 'Nucleic acids'), 2, 'Almost all enzymes are proteins (some RNA ribozymes exist, but the exam answer is proteins).'),
            array($course_ids[3], 'MDCAT', 'Normal human body temperature is about:', array('35 °C', '37 °C', '40 °C', '27 °C'), 1, 'Typical core temperature is approximately 37 °C.'),
            array($course_ids[3], 'MDCAT', 'The powerhouse of the cell is the:', array('Nucleus', 'Mitochondrion', 'Lysosome', 'Vacuole'), 1, 'Mitochondria produce most of the cell’s ATP.'),
            array($course_ids[3], 'MDCAT', 'Blood group AB has:', array('Anti-A and anti-B', 'No antigens', 'A and B antigens, no anti-A/anti-B', 'Only Rh antigen'), 2, 'AB individuals have A and B antigens and neither antibody.'),
            array($course_ids[3], 'MDCAT', 'Which is a communicable disease?', array('Diabetes mellitus', 'Hypertension', 'Tuberculosis', 'Scurvy'), 2, 'TB is infectious; the others listed are not.'),
            array($course_ids[4], 'Algebra', 'The solution of 2x + 6 = 0 is:', array('x = 3', 'x = -3', 'x = 6', 'x = -6'), 1, '2x = -6 ⇒ x = -3.'),
            array($course_ids[4], 'Algebra', 'If a² − b² = (a − b)(a + b), then 9 − 4 equals:', array('(3-2)(3+2)', '(9-2)(9+2)', '13', '5 only, with no factorisation'), 0, 'Difference of squares: 9−4 = (3−2)(3+2).'),
            array($course_ids[4], 'Functions', 'The slope of y = 3x + 2 is:', array('2', '3', '5', '0'), 1, 'In y = mx + c, m is the slope.'),
            array($course_ids[4], 'Calculus', 'The derivative of x² with respect to x is:', array('x', '2x', '2', 'x²'), 1, 'd/dx (x²) = 2x.'),
            array($course_ids[4], 'Trig', 'sin 90° equals:', array('0', '1', '1/2', '√3/2'), 1, 'sin 90° = 1.'),
            array($course_ids[4], 'Algebra', 'The quadratic formula for ax²+bx+c=0 is x =', array('(-b±√(b²-4ac))/2a', 'b/2a', '-c/a', '(a+b)/c'), 0, 'Standard quadratic formula.'),
            array($course_ids[4], 'Sequences', 'The next term of 2, 4, 8, 16 is:', array('18', '24', '32', '20'), 2, 'Geometric sequence with ratio 2.'),
            array($course_ids[4], 'Geometry', 'The sum of interior angles of a triangle is:', array('90°', '180°', '270°', '360°'), 1, 'A triangle’s interior angles sum to 180°.'),
            array($course_ids[4], 'Calculus', '∫ 2x dx equals:', array('x² + C', '2x² + C', 'x + C', '2 + C'), 0, 'Antiderivative of 2x is x² + C.'),
            array($course_ids[4], 'Algebra', 'If log₁₀ 100 = x, then x is:', array('1', '2', '10', '100'), 1, '10² = 100, so log₁₀ 100 = 2.'),
            array($course_ids[5], 'Grammar', 'Choose the correct article: ___ honest person.', array('a', 'an', 'the only never', 'no article required always'), 1, '“Honest” begins with a vowel sound, so “an”.'),
            array($course_ids[5], 'Grammar', 'The past participle of “write” is:', array('wrote', 'written', 'writing', 'writes'), 1, 'write / wrote / written.'),
            array($course_ids[5], 'Comprehension', 'A synonym of “precise” is:', array('vague', 'exact', 'noisy', 'late'), 1, 'Precise means exact or accurate.'),
            array($course_ids[5], 'Grammar', 'Which is a complete sentence?', array('Running quickly.', 'Because the rain.', 'The council met at dawn.', 'Although tired.'), 2, 'Only “The council met at dawn.” has a subject and finite verb.'),
            array($course_ids[5], 'Vocab', 'An antonym of “scarce” is:', array('rare', 'abundant', 'hidden', 'costly'), 1, 'Scarce ↔ abundant.'),
            array($course_ids[5], 'Writing', 'A topic sentence usually appears:', array('Only in footnotes', 'At the start of a paragraph', 'In the bibliography', 'In the page number'), 1, 'Topic sentences typically open a paragraph.'),
            array($course_ids[5], 'Grammar', '“Neither of the answers ___ correct.”', array('are', 'is', 'were being', 'have'), 1, '“Neither” takes a singular verb in formal usage: is.'),
            array($course_ids[5], 'Vocab', '“Council” in PKCouncil refers most nearly to:', array('A noisy crowd', 'An advisory academic body', 'A type of coupon', 'A payment gateway'), 1, 'A council is a body that deliberates and advises.'),
            array($course_ids[5], 'Grammar', 'Identify the verb: “Students practise daily.”', array('Students', 'practise', 'daily', 'the period'), 1, '“Practise” is the verb.'),
            array($course_ids[5], 'Comprehension', 'Tone that is “academic” is typically:', array('Slang-heavy', 'Measured and precise', 'Aggressive', 'Sarcastic only'), 1, 'Academic tone is measured, precise, and evidence-led.'),
        );
        global $wpdb;
        $now = PKC_DB::now();
        foreach ($bank as $q) {
            $wpdb->insert(PKC_DB::questions(), array(
                'course_id' => $q[0], 'topic' => $q[1], 'subject' => $q[1], 'category' => 'MCQ',
                'question' => $q[2], 'explanation' => $q[5], 'marks' => 1, 'difficulty' => 'medium',
                'status' => 'active', 'created_at' => $now, 'updated_at' => $now,
            ));
            $qid = (int) $wpdb->insert_id;
            foreach ($q[3] as $oi => $ot) {
                $wpdb->insert(PKC_DB::question_options(), array(
                    'question_id' => $qid, 'option_text' => $ot, 'is_correct' => ($oi === $q[4]) ? 1 : 0, 'sort_order' => $oi,
                ));
            }
        }
    }
}
