<?php

/*
 * @author akastl<anja.kastl@gmail.com>
 * 
 * Import Post
 * Laravel Task - triggered by cronjob
 * Import Wordpress post into a database. Powered by a json feed.
 */

class importpost_Task {
    
    public $categories,
           $tags;
    
    public $feedback = array();
    
    private $env;
    
    public function __construct()
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('memory_limit', '-1');    
        
        date_default_timezone_set('America/New_York');
        
        $this->env = $this->setEnvironment();
    }
    
    public function run($arguments) 
    {
        Log::write('info','Voters Grid: start importpost');

        $posts = array();
        
        $projects = $this->getProjects();
        
        $allitems = $this->getExistingPosts(); //

        foreach ($projects as $project)
        {
            $posts[$project->slug] = array();
            $postincrements = array();
            $posts_per_page = 10;
            $page = 1;
            do {
                $postincrements = $this->getPostsPaginated($project->slug, $page);

                $posts_per_page = count($postincrements);
                $page++;

                if ($posts_per_page > 0)
                {
                    $posts[$project->slug] = array_merge($posts[$project->slug], $postincrements);
                }
            } while ($posts_per_page == 10);

            echo 'number of posts: ' .count($posts[$project->slug]). "\n\n";
            Log::write('info','Voters Grid: number of posts -'.count($posts[$project->slug]));

            // $posts[$project->slug] = $this->getPosts($project->slug);
            
            $posts[$project->slug] = $this->processPosts($posts[$project->slug], $project->slug, $allitems);

            $this->enterData($posts[$project->slug], $project->slug);
        }
        
        return $posts;
    }
    
    public function getPosts($slug)
    {   
        $url = "http://".$this->env."/votegrid/wp-json/votes?filter[post_status]=publish&filter[posts_per_page]=10000&filter[voters_grid]={$slug}";
        echo $url. "\n";
        Log::write('info','Voters Grid: '.$url);
        $data = json_decode(@file_get_contents($url));
        
        return $data;
    }

    public function getPostsPaginated($slug, $page)
    {   
        $url = "http://".$this->env."/votegrid/wp-json/votes?filter[post_status]=publish&page={$page}&filter[voters_grid]={$slug}";
        echo $url. "\n";
        Log::write('info','Voters Grid: '.$url);
        $data = json_decode(@file_get_contents($url));
        
        return $data;
    }
    
    public function getExistingPosts()
    {
        $data = DB::table('voter_grid.posts')
                ->join("ndwp.nd_3_posts", "nd_3_posts.ID", "=", "wp_id")
//                ->where("nd_3_posts.post_status", "=", "publish")       
                ->get(array(
                    "wp_id",
                    "modified")
                );

        $this->dbResponse($data, 'Could not get vote_grid items.');
        
        return $data;
    }
    
    public function processPosts($data, $slug, $allitems)
    {
        $postdata = array();
        $this->categories = array();
        $this->tags = array();
        
        if (count($data) > 0)
        {
            foreach ($data as $key => $post)
            {
                $data = $this->getProjectTaxonomy($data, $key, 'voting_category');
                $data = $this->getProjectTaxonomy($data, $key, 'voting_tag');

                $found = $this->searchValue($allitems, $post->ID, $post->modified);

                $postdata[$found][] = $this->insertData($data, $key, $slug);
            }
        }
        
        return $postdata;
    }
    
    /*
     * $allitems - all posts in voter_grid database
     * $post - current post id from the wordpress database
     * $date - current modified date from the wordpress database
     */
    public function searchValue($allitems, $post, $date)
    {
        foreach ($allitems as $key => $item)
        {
            $modified = $item->modified - strtotime($date);
            
            if ($item->wp_id == $post && $modified >= 0)
            {
                return 'delete';
            }
            
            if ($item->wp_id == $post && $modified < 0)
            {
                return 'update';
            }
        }
        
        return 'insert';
    }
    
    public function enterData($data, $slug)
    {
        foreach ($data as $key => $item)
        {
            if ($key == 'insert')
            {
                $this->insertPosts($item);
                $this->updateTaxonomyItems($slug);
            }
            
            if ($key == 'update')
            {
                foreach ($item as $post)
                {
                    $this->updatePost($post);
                    $this->updateTaxonomyItems($slug);
                }               
            }

            if($key == 'delete')
            {
                foreach ($item as $post)
                {
                    $this->updateTaxonomyItems($slug);
                    $this->updateSlugIems($post);
                }
            }
        }
    }
    
    public function insertData($data, $key, $slug)
    {
        $postdata = array(
            'project_slug' => $slug,
            'wp_id' => (isset($data[$key]->ID)) ? $data[$key]->ID : null,
            'slug' => (isset($data[$key]->slug)) ? $data[$key]->slug : null,
            'title' => (isset($data[$key]->title)) ? $data[$key]->title : null,
            'content' => (isset($data[$key]->content)) ? $data[$key]->content : null,
            'post_status' => (isset($data[$key]->status)) ? $data[$key]->status : null,
            'date' => (isset($data[$key]->date)) ? strtotime($data[$key]->date) : null,
            'modified' => (isset($data[$key]->modified)) ? strtotime($data[$key]->modified) : null,
            'excerpt' => (isset($data[$key]->excerpt)) ? $data[$key]->excerpt : null,
            'large_image_path' => (isset($data[$key]->large_image_path)) ? $data[$key]->large_image_path : null,
            'tb_image_path' => (isset($data[$key]->tb_image_path)) ? $data[$key]->tb_image_path : null,
            'media_credit' => (isset($data[$key]->media_credit)) ? $data[$key]->media_credit : null,
            'video_embed' => (isset($data[$key]->video_embed)) ? $data[$key]->video_embed : null,
            'categories' => (isset($data[$key]->categories)) ? $data[$key]->categories : null,
            'tags' => (isset($data[$key]->tags)) ? $data[$key]->tags : null,
        );
        
        return $postdata;
    }


    public function insertPosts($posts)
    {
        $insert = DB::table('voter_grid.posts')
                ->insert($posts);
        
        $this->dbResponse($insert, 'Could not insert posts');
    }
    
    public function updatePost($post)
    {
        $update = DB::table('voter_grid.posts')
                ->where('wp_id', '=', $post['wp_id'])
                ->update($post);
        
        $this->dbResponse($update, 'Could not update posts');
    }
    
    public function updateSlugIems($post)
    {
        $updatedata = array(
            'project_slug' => $post['project_slug'],
            'wp_id' => $post['wp_id'],
        );
        
        $update = DB::table('voter_grid.posts')
                ->where('wp_id', '=', $post['wp_id'])
                ->update($updatedata);
        
        $this->dbResponse($update, 'Could not update slugs on posts');
    }


    public function getProjects()
    {
        $data = DB::table('voter_grid.projects')       
                ->get(array("slug"));
        
        $this->dbResponse($data, 'Could not get project taxonomy from voter_grid.');
        
        return $data;
    }
    
    public function getProjectTaxonomy($data, $key, $property)
    {
        $taxonomy = 'categories';
        if ($property == 'voting_tag')
        {
            $taxonomy = 'tags';
        }
        
        $data[$key]->{$taxonomy} = '';
        
        if (!isset($data[$key]->terms->{$property}))
        {
            return $data;
        }
        
        // get all project categories
        foreach($data[$key]->terms->{$property} as $term)
        {
            if(!in_array($term->name, $this->{$taxonomy}))
            {
                $this->{$taxonomy}[$term->slug] = $term->name;
            }
            
            $data[$key]->{$taxonomy} = $data[$key]->{$taxonomy}. "[{$term->slug}]{$term->name}";
        }
        
        return $data;
    }
    
    public function updateTaxonomyItems($slug)
    {
        $tax = array();
        $tax['categories'] = '';
        $tax['tags'] = '';
        
        foreach ($this->categories as $key => $cat)
        {
            $tax['categories'] = $tax['categories']. "[{$key}]{$cat}";
        }
        
        foreach ($this->tags as $key => $tag)
        {
            $tax['tags'] = $tax['tags']. "[{$key}]{$tag}";
        }
        
        $update = DB::table('voter_grid.projects')
                ->where('slug', '=', $slug)
                ->update($tax);
        
        $this->dbResponse($update, 'Could not update projects with taxonomy terms');
    }

    /**
     * Check query response for errors
     */
    public function dbResponse($response, $data)
    {
        if ($response===false)
        {
            $this->feedback['errors'][] = $data;
        }
    }
    
    private function setEnvironment()
    {
        $env = gethostname();

        switch ($env)
        {
            case 'nd-webserver1' :
                $env = 'dev.projects.newsday.com';
                break;
            case 'cvldndweb1' :
            case 'ip-10-176-251-98.cablevision.com':
            case 'ip-10-176-251-161.cablevision.com':
                $env = 'stage.projects.newsday.com';
                break;
            case 'ubuntu':
                $env = 'local.projects.newsday.com';
                break;
            default :
                $env = 'projects.newsday.com';
                break;
        }
        
        return $env;
    }
}

?> 