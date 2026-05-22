<?php
/**
* Professional Git Deployment Console for cPanel
* Created for: it.noorgee.com/FU
* Date: 03-Feb-2026
* SECURITY: Session Based (No URL Key Required)
*/


// Force browser to refresh and ignore old 403 cache
header("Cache-Control: no-cache, must-revalidate");
session_start();
date_default_timezone_set('Asia/Karachi');


// --- CONFIGURATION ---
$repo_path   = '/home/noorgeec/repositories/fuel-repo';       // Path containing .git
$work_tree   = '/home/noorgeec/it.noorgee.com/FU';           // Live public directory
$branch      = 'main-fu';                                    // Target branch
$pass_code   = '123';                                        // UI Login Password
$repo_url    = 'https://github.com/grapheart247/fuel-count'; // GitHub Repo Link


// --- LOGIN HANDLER ---
if (isset($_POST['login_pass'])) {
   if ($_POST['login_pass'] === $pass_code) {
       $_SESSION['authenticated_deploy'] = true;
       header("Location: deploy.php");
       exit;
   } else {
       $login_error = "Invalid Password";
   }
}


// --- LOGOUT HANDLER ---
if (isset($_GET['logout'])) {
   unset($_SESSION['authenticated_deploy']);
   session_destroy();
   header("Location: deploy.php");
   exit;
}


// --- ACCESS CONTROL ---
if (!isset($_SESSION['authenticated_deploy']) || $_SESSION['authenticated_deploy'] !== true) {
   ?>
   <!DOCTYPE html>
   <html>
   <head>
       <meta name="viewport" content="width=device-width, initial-scale=1.0">
       <script src="https://cdn.tailwindcss.com"></script>
       <title>Login | NG Tool</title>
   </head>
   <body class="bg-slate-900 h-screen flex items-center justify-center p-4 font-sans">
       <form method="POST" class="bg-white p-8 rounded-2xl shadow-2xl w-full max-w-sm border-t-4 border-blue-600">
           <div class="text-center mb-6">
               <div class="bg-blue-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 text-blue-600">
                   <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
               </div>
               <h1 class="font-bold text-xl text-slate-800">Fuel Console</h1>
               <p class="text-slate-500 text-sm">Deployment Dashboard</p>
           </div>
           <?php if(isset($login_error)): ?>
               <div class="bg-red-50 text-red-600 p-3 rounded-lg mb-4 text-xs text-center font-bold border border-red-100 italic">
                   <?= $login_error ?>
               </div>
           <?php endif; ?>
           <div class="space-y-4">
               <input type="password" name="login_pass" placeholder="System Password" class="w-full border-2 p-3 rounded-xl text-center focus:border-blue-500 outline-none transition" autofocus required>
               <button type="submit" class="w-full bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition shadow-lg shadow-blue-200">Unlock Console</button>
           </div>
       </form>
   </body>
   </html>
   <?php
   exit;
}


// --- EXECUTION ENGINE ---
function execute_command($cmd, $title) {
   $full_cmd = $cmd . " 2>&1";
   exec($full_cmd, $output, $return_var);
   return [
       'title' => $title,
       'command' => $cmd,
       'output' => implode("\n", $output),
       'status' => ($return_var === 0) ? 'success' : 'error'
   ];
}


$history = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
   $act = $_POST['action'];


   // Action: Pull & Deploy
   if ($act === 'sync') {
       $history[] = execute_command("cd $repo_path && git fetch origin $branch", "Fetch Origin");
       $history[] = execute_command("cd $repo_path && git reset --hard origin/$branch", "Hard Reset Local Repo");
       $history[] = execute_command("git --git-dir=$repo_path/.git --work-tree=$work_tree checkout -f $branch", "Work-Tree Live Deployment");
   }


   // Action: Status
   if ($act === 'status') {
       $history[] = execute_command("cd $repo_path && git status", "Repository Status");
   }


   // Action: Push
   if ($act === 'push') {
       $history[] = execute_command("git --git-dir=$repo_path/.git --work-tree=$work_tree add .", "Staging Changes");
       $history[] = execute_command("git --git-dir=$repo_path/.git --work-tree=$work_tree commit -m 'Panel Update: " . date('Y-m-d H:i') . "'", "Commit Live Changes");
       $history[] = execute_command("cd $repo_path && git push origin $branch", "Push to GitHub");
   }


   // Action: Revert
   if ($act === 'revert' && !empty($_POST['commit_hash'])) {
       $hash = escapeshellarg($_POST['commit_hash']);
       $history[] = execute_command("cd $repo_path && git reset --hard $hash", "Reverting Repo to $hash");
       $history[] = execute_command("git --git-dir=$repo_path/.git --work-tree=$work_tree checkout -f $hash", "Applying Revert to Live Site");
   }
}


// --- DATA FETCHING ---
// Get Latest Commit Details (Hash|Timestamp|Subject|Body)
$log_raw = @shell_exec("cd $repo_path && git log -1 --format='%H|%at|%s|%b' 2>&1");
$commit_data = explode('|', (string)$log_raw);


// Get Commit List for Revert Dropdown (Last 15)
$list_raw = @shell_exec("cd $repo_path && git log -n 15 --format='%h|%s|%ar' 2>&1");
$commit_lines = array_filter(explode("\n", (string)$list_raw));


function time_elapsed_string($datetime) {
   $now = new DateTime;
   $ago = new DateTime("@$datetime");
   $diff = $now->diff($ago);
   $string = ['y'=>'yr','m'=>'mo','d'=>'day','h'=>'hr','i'=>'min'];
   foreach ($string as $k => &$v) {
       if ($diff->$k) { $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : ''); }
       else { unset($string[$k]); }
   }
   $string = array_slice($string, 0, 1);
   return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <script src="https://cdn.tailwindcss.com"></script>
   <title>Git Pipeline | NG Tool</title>
   <style>
       .terminal { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
   </style>
</head>
<body class="bg-slate-50 p-4 md:p-8">
   <div class="max-w-6xl mx-auto">
       <!-- Header -->
       <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 bg-white p-6 rounded-2xl shadow-sm border">
           <div>
               <h1 class="text-2xl font-black text-slate-800 tracking-tight flex items-center gap-2">
                   <span class="bg-blue-600 w-3 h-8 rounded-full"></span>
                   Git Pipeline Console
               </h1>
               <p class="text-xs text-slate-400 mt-1 uppercase font-bold tracking-widest">it.noorgee.com/FU &bull; Karachi Zone</p>
           </div>
           <div class="flex gap-2">
               <a href="/FU" target="_blank" class="px-4 py-2 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold hover:bg-slate-200 transition">View Site</a>
               <a href="?logout=1" class="px-4 py-2 bg-red-50 text-red-600 rounded-xl text-xs font-bold hover:bg-red-100 transition">Logout</a>
           </div>
       </div>


       <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
           <!-- Sidebar -->
           <div class="lg:col-span-4 space-y-6">
               <!-- Info Card -->
               <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                   <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-4">Latest Build Info</h2>
                   <?php if(count($commit_data) >= 3): ?>
                       <div class="space-y-4">
                           <div>
                               <label class="text-[9px] text-slate-400 uppercase font-bold">Commit ID</label>
                               <div class="text-xs font-mono text-blue-600 break-all bg-blue-50 p-2 rounded-lg mt-1 border border-blue-100">
                                   <?= htmlspecialchars($commit_data[0]) ?>
                               </div>
                           </div>
                           <div class="flex justify-between">
                               <div>
                                   <label class="text-[9px] text-slate-400 uppercase font-bold">Date & Time</label>
                                   <div class="text-xs font-bold text-slate-700"><?= date('d M Y, H:i', (int)$commit_data[1]) ?></div>
                               </div>
                               <div class="text-right">
                                   <label class="text-[9px] text-slate-400 uppercase font-bold">Age</label>
                                   <div class="text-xs font-bold text-green-600"><?= time_elapsed_string((int)$commit_data[1]) ?></div>
                               </div>
                           </div>
                           <div>
                               <label class="text-[9px] text-slate-400 uppercase font-bold">Subject</label>
                               <div class="text-sm font-bold text-slate-800"><?= htmlspecialchars($commit_data[2]) ?></div>
                           </div>
                           <?php if(!empty($commit_data[3])): ?>
                           <div>
                               <label class="text-[9px] text-slate-400 uppercase font-bold">Description</label>
                               <div class="text-xs text-slate-500 italic mt-1"><?= nl2br(htmlspecialchars($commit_data[3])) ?></div>
                           </div>
                           <?php endif; ?>
                       </div>
                   <?php else: ?>
                       <div class="p-4 bg-red-50 text-red-600 rounded-xl border border-red-100 text-xs font-bold italic text-center">Repository Not Initialized</div>
                   <?php endif; ?>
               </div>


               <!-- Actions -->
               <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 space-y-3">
                   <h2 class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Operations</h2>
                   <form method="POST" onsubmit="return confirm('Sync live directory with GitHub?')">
                       <input type="hidden" name="action" value="sync">
                       <button class="w-full bg-blue-600 text-white p-4 rounded-xl font-bold text-sm hover:bg-blue-700 transition flex items-center justify-center gap-2 shadow-lg shadow-blue-100">
                           <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path></svg>
                           Pull & Deploy
                       </button>
                   </form>
                   <div class="grid grid-cols-2 gap-3">
                       <form method="POST">
                           <input type="hidden" name="action" value="status">
                           <button class="w-full bg-slate-800 text-white py-3 rounded-xl font-bold text-xs hover:bg-black transition">Status</button>
                       </form>
                       <form method="POST" onsubmit="return confirm('Push local panel changes to GitHub?')">
                           <input type="hidden" name="action" value="push">
                           <button class="w-full bg-white border-2 text-slate-700 py-3 rounded-xl font-bold text-xs hover:bg-slate-50 transition">Push Site</button>
                       </form>
                   </div>


                   <div class="pt-4 border-t mt-4">
                       <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3 block">Restore System</label>
                       <form method="POST" class="space-y-2">
                           <input type="hidden" name="action" value="revert">
                           <select name="commit_hash" class="w-full p-3 border rounded-xl text-xs font-mono bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                               <option value="">Select a version...</option>
                               <?php foreach($commit_lines as $line):
                                   $p = explode('|', $line); ?>
                                   <option value="<?= $p[0] ?>"><?= $p[0] ?> - <?= substr($p[1], 0, 30) ?> (<?= $p[2] ?>)</option>
                               <?php endforeach; ?>
                           </select>
                           <button type="submit" onclick="return confirm('WARNING: This will force rollback the live site. Proceed?')" class="w-full bg-orange-500 text-white py-3 rounded-xl font-bold text-xs hover:bg-orange-600 transition shadow-lg shadow-orange-100">Restore Selection</button>
                       </form>
                   </div>
               </div>
           </div>


           <!-- Terminal -->
           <div class="lg:col-span-8">
               <div class="bg-[#0d1117] rounded-2xl shadow-xl overflow-hidden min-h-[550px] flex flex-col border border-slate-800">
                   <div class="bg-[#161b22] px-4 py-3 flex items-center justify-between border-b border-white/5">
                       <div class="flex items-center gap-2">
                           <div class="flex gap-1.5">
                               <div class="w-2.5 h-2.5 rounded-full bg-red-500/80"></div>
                               <div class="w-2.5 h-2.5 rounded-full bg-yellow-500/80"></div>
                               <div class="w-2.5 h-2.5 rounded-full bg-green-500/80"></div>
                           </div>
                           <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest ml-4">Deployment Terminal Logs</span>
                       </div>
                       <span class="text-[9px] font-mono text-slate-600">user@noorgee:~/it.noorgee.com/FU</span>
                   </div>
                   <div class="terminal p-6 text-slate-300 text-xs leading-relaxed overflow-y-auto flex-grow">
                       <?php if(empty($history)): ?>
                           <div class="flex flex-col items-center justify-center h-full opacity-20 py-20">
                               <svg class="w-16 h-16 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                               <p class="text-sm font-bold uppercase tracking-tighter italic text-center">Waiting for Command Execution...</p>
                           </div>
                       <?php else: ?>
                           <?php foreach($history as $h): ?>
                               <div class="mb-8 animate-in slide-in-from-bottom-2 duration-300">
                                   <div class="flex items-center gap-2 mb-2">
                                       <span class="text-[9px] px-2 py-0.5 rounded font-black tracking-tighter <?= $h['status']=='success' ? 'bg-green-500/20 text-green-500' : 'bg-red-500/20 text-red-500' ?>">
                                           [<?= strtoupper($h['status']) ?>]
                                       </span>
                                       <span class="text-slate-500 font-bold text-[10px] uppercase"><?= htmlspecialchars($h['title']) ?></span>
                                   </div>
                                   <div class="text-blue-400 mb-2 font-bold select-none">$ <?= htmlspecialchars($h['command']) ?></div>
                                   <pre class="opacity-80 whitespace-pre-wrap bg-white/5 p-4 rounded-lg border border-white/5"><?= htmlspecialchars($h['output']) ?: 'Done (No output)' ?></pre>
                               </div>
                           <?php endforeach; ?>
                       <?php endif; ?>
                   </div>
               </div>
           </div>
       </div>
   </div>
</body>
</html>
