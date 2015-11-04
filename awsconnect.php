<?php

require(dirname(dirname(dirname(__FILE__))) . "/aws/aws-autoloader.php");

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;


class AwsConnect 
{
    public $s3;
    public $s3Client;
    public $uploadDir;

    public function __construct($args = array())
	{
        $this->s3 = S3Client::factory($this->build_aws_config());
        $this->s3Client = $this->s3->get('s3');

        $this->uploadDir = '/maps/icons';
        $this->bucket = 'assets.projects.newsday.com';
	}

    public function build_aws_config() 
    {
        return array(
            'key' => getenv('S3_KEY'),
            'secret' => getenv('S3_SECRET'),
            'region' => 'us-east-1'
        );
    }

        //upload a series of keys to S3
    public function push_to_s3($file, $args) 
    {
        set_time_limit(120);
        $errors = 0;

        $filename = 'dot_'.$args['color'].'_'.$args['text'].'.png';
        $remoteFile = $this->uploadDir. '/' .$filename;

        try 
        {
            // Upload data.
            $marker = array(
                'Bucket' => $this->bucket,
                'Key'    => $remoteFile,
                'Body'   => $file,
                'ACL'    => 'public-read'
            );

            $result = $this->s3->putObject($marker);

            // Print the URL to the object.
            // echo $result['ObjectURL'];

        } catch (MultipartUploadException $e) 
        {
            $uploader->abort();
            echo "Upload failed.";
            echo "<pre>".$e->getMessage()."</pre>";
            $errors++;
        }

        return ($errors == 0) ? 'http://'.$this->bucket.$remoteFile : false;
    }
}

?>
